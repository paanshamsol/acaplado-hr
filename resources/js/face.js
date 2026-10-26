import * as faceapi from '@vladmandic/face-api';

const MODEL_URL = '/models';

/**
 * Detection passes tried in order. The tiny detector's default (416px, 0.5)
 * misses faces in many real profile photos (side angles, portrait shots), so
 * enrollment retries at larger input sizes and lower score thresholds until a
 * face is found. Larger input sizes are slower, hence the fallback ordering.
 */
const IMAGE_DETECTION_PASSES = [
    { inputSize: 608, scoreThreshold: 0.4 },
    { inputSize: 512, scoreThreshold: 0.35 },
    { inputSize: 416, scoreThreshold: 0.3 },
    { inputSize: 320, scoreThreshold: 0.25 },
];

const VIDEO_DETECTION_PASSES = [
    { inputSize: 416, scoreThreshold: 0.5 },
    { inputSize: 608, scoreThreshold: 0.35 },
];

let modelsPromise = null;

/**
 * Explain why the camera API is unavailable, if it is.
 */
export function cameraSupportMessage() {
    if (!window.isSecureContext) {
        const host = window.location.host || window.location.hostname;
        return `The camera requires a secure (HTTPS) connection. Open https://${host} or use http://localhost.`
    }

    if (!navigator.mediaDevices?.getUserMedia) {
        return 'This browser does not support camera access. Use Chrome, Edge, Safari or Firefox.';
    }

    return '';
}

/**
 * Load the face detection / landmark / recognition models once.
 */
export function loadFaceModels() {
    if (!modelsPromise) {
        modelsPromise = Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);
    }

    return modelsPromise;
}

/**
 * Detect the largest face in a video element or image, retrying with several
 * detector settings until one succeeds.
 *
 * @returns {Promise<object|null>}
 */
async function detectLargestFace(source, passes) {
    for (const options of passes) {
        const detections = await faceapi
            .detectAllFaces(source, new faceapi.TinyFaceDetectorOptions(options))
            .withFaceLandmarks()
            .withFaceDescriptors();

        if (detections.length) {
            return detections.reduce((best, current) =>
                current.detection.box.area > best.detection.box.area ? current : best,
            );
        }
    }

    return null;
}

/**
 * Compute a 128-d face descriptor from a video element.
 *
 * @returns {Promise<number[]|null>}
 */
export async function detectDescriptor(video) {
    const detection = await detectLargestFace(video, VIDEO_DETECTION_PASSES);

    if (!detection) {
        return null;
    }

    return Array.from(detection.descriptor);
}

/**
 * Compute a 128-d face descriptor from a still image element (e.g. a profile photo).
 *
 * @returns {Promise<number[]|null>}
 */
export async function detectDescriptorFromImage(image) {
    const detection = await detectLargestFace(image, IMAGE_DETECTION_PASSES);

    if (!detection) {
        return null;
    }

    return Array.from(detection.descriptor);
}

/**
 * Load an HTMLImageElement from a URL (or blob URL), resolving once decoded.
 */
export function loadImage(url, options = {}) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.crossOrigin = options.crossOrigin !== undefined ? options.crossOrigin : 'anonymous';
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('Unable to load image.'));
        image.src = url;
    });
}

/**
 * Grab a JPEG data URL snapshot from the video element.
 */
export function captureSnapshot(video, canvas, quality = 0.9) {
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;

    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

    return canvas.toDataURL('image/jpeg', quality);
}

/**
 * Alpine component used by the admin to enroll a staff member's face.
 */
export function faceEnroll(alreadyEnrolled = false, autoSyncUrl = '') {
    return {
        ready: false,
        streaming: false,
        busy: false,
        enrolled: alreadyEnrolled,
        hadEnrollment: alreadyEnrolled,
        descriptor: '',
        preview: '',
        message: '',
        error: '',
        cameraWarning: '',
        stream: null,
        photoBusy: false,
        autoSyncUrl,
        started: false,

        async init() {
            if (this.started) {
                return;
            }

            this.started = true;
            this.cameraWarning = cameraSupportMessage();

            try {
                this.message = 'Loading face models…';
                await loadFaceModels();
                this.ready = true;
                this.message = this.hadEnrollment
                    ? 'A face is already enrolled. Capture again to replace it.'
                    : 'Models ready. Start the camera to enroll a face.';
            } catch (e) {
                this.error = 'Failed to load the face recognition models.';
                return;
            }

            if (this.autoSyncUrl) {
                await this.syncFromPhotoUrl(this.autoSyncUrl, true);
            }
        },

        async start() {
            this.error = '';
            try {
                if (!navigator.mediaDevices?.getUserMedia) {
                    this.error = 'Camera is not available in this browser. Use a secure (HTTPS or localhost) connection.';
                    return;
                }

                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: 640, height: 480 },
                    audio: false,
                });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.streaming = true;
                this.message = 'Look straight at the camera, then press Capture.';
            } catch (e) {
                this.error = this.cameraErrorMessage(e);
            }
        },

        cameraErrorMessage(error) {
            if (!error) {
                return 'Unable to access the camera.';
            }

            switch (error.name) {
                case 'NotAllowedError':
                case 'PermissionDeniedError':
                case 'SecurityError':
                    return 'Camera permission denied. Allow camera access for this site, then refresh and press Start Camera again.';
                case 'NotFoundError':
                case 'DevicesNotFoundError':
                    return 'No camera was found on this device.';
                case 'NotReadableError':
                case 'TrackStartError':
                    return 'The camera is in use by another application. Close it and try again.';
                case 'OverconstrainedError':
                case 'ConstraintNotSatisfiedError':
                    return 'The camera does not meet the required settings.';
                case 'TypeError':
                    return 'Camera failed to start. Make sure the site is served over HTTPS or localhost.';
                default:
                    return `Unable to access the camera (${error.name || 'error'}): ${error.message || 'unknown reason'}`;
            }
        },

        stop() {
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }
            this.streaming = false;
        },

        async capture() {
            this.busy = true;
            this.error = '';
            try {
                const descriptor = await detectDescriptor(this.$refs.video);

                if (!descriptor) {
                    this.error = 'No face detected. Improve lighting and try again.';
                    return;
                }

                this.descriptor = JSON.stringify(descriptor);
                this.preview = captureSnapshot(this.$refs.video, this.$refs.canvas);
                this.enrolled = true;
                this.message = 'Face captured. Remember to save the form.';
            } catch (e) {
                this.error = 'Face capture failed. Please try again.';
            } finally {
                this.busy = false;
            }
        },

        async onPhotoSelected(event) {
            const file = event?.target?.files?.[0];

            if (!file) {
                return;
            }

            this.photoBusy = true;
            this.error = '';
            this.message = 'Scanning the profile photo for a face…';

            const objectUrl = URL.createObjectURL(file);

            try {
                await loadFaceModels();
                const image = await loadImage(objectUrl);
                await this.enrollFromImage(image);
            } catch (e) {
                this.error = 'Could not read the profile photo. Try the webcam instead.';
            } finally {
                URL.revokeObjectURL(objectUrl);
                this.photoBusy = false;
            }
        },

        async syncFromPhotoUrl(url, autoSave = false) {
            if (!url) {
                return;
            }

            this.photoBusy = true;
            this.error = '';
            this.message = 'Scanning the current profile photo for a face…';

            try {
                await loadFaceModels();
                let image;
                try {
                    image = await loadImage(url, { crossOrigin: 'anonymous' });
                } catch (e) {
                    image = await loadImage(url, { crossOrigin: null });
                }
                await this.enrollFromImage(image);

                if (autoSave && this.descriptor) {
                    this.message = 'Face synced. Saving…';
                    this.$el.closest('form')?.submit();
                }
            } catch (e) {
                this.error = 'Could not read the profile photo. Download the photo and upload it via Choose file, or use the webcam to enroll instead.';
            } finally {
                this.photoBusy = false;
            }
        },

        async enrollFromImage(image) {
            const descriptor = await detectDescriptorFromImage(image);

            if (!descriptor) {
                this.error = 'No face detected in the photo. Try uploading the image again or use the webcam.';
                this.message = '';
                return;
            }

            this.descriptor = JSON.stringify(descriptor);
            this.preview = this.imagePreview(image);
            this.enrolled = true;
            this.error = '';
            this.message = 'Face synced from the profile photo. Save the form to store it.';
        },

        imagePreview(image) {
            const canvas = this.$refs.canvas;
            const size = 256;
            canvas.width = size;
            canvas.height = size;

            const context = canvas.getContext('2d');
            const scale = Math.min(size / image.width, size / image.height);
            const width = image.width * scale;
            const height = image.height * scale;

            context.drawImage(image, (size - width) / 2, (size - height) / 2, width, height);

            return canvas.toDataURL('image/jpeg', 0.9);
        },

        clear() {
            this.descriptor = '';
            this.preview = '';
            this.enrolled = this.hadEnrollment;
            this.message = 'Captured face removed.';
        },
    };
}

/**
 * Alpine component for the shared clock-in / clock-out kiosk.
 */
export function clockPunch({ punchUrl, csrf }) {
    return {
        ready: false,
        streaming: false,
        busy: false,
        staffId: '',
        password: '',
        message: '',
        error: '',
        cameraWarning: '',
        result: null,
        stream: null,

        async init() {
            this.cameraWarning = cameraSupportMessage();

            try {
                await loadFaceModels();
                this.ready = true;
            } catch (e) {
                this.error = 'Failed to load the face recognition models.';
            }
        },

        async start() {
            this.error = '';
            try {
                if (!navigator.mediaDevices?.getUserMedia) {
                    this.error = 'Camera is not available in this browser. Use a secure (HTTPS or localhost) connection.';
                    return;
                }

                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: 640, height: 480 },
                    audio: false,
                });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.streaming = true;
                this.message = 'Camera is on. Enter your staff ID and password, then punch.';
            } catch (e) {
                this.error = this.cameraErrorMessage(e);
            }
        },

        cameraErrorMessage(error) {
            if (!error) {
                return 'Unable to access the camera.';
            }

            switch (error.name) {
                case 'NotAllowedError':
                case 'PermissionDeniedError':
                case 'SecurityError':
                    return 'Camera permission denied. Allow camera access for this site, then refresh and press Start Camera again.';
                case 'NotFoundError':
                case 'DevicesNotFoundError':
                    return 'No camera was found on this device.';
                case 'NotReadableError':
                case 'TrackStartError':
                    return 'The camera is in use by another application. Close it and try again.';
                case 'OverconstrainedError':
                case 'ConstraintNotSatisfiedError':
                    return 'The camera does not meet the required settings.';
                case 'TypeError':
                    return 'Camera failed to start. Make sure the site is served over HTTPS or localhost.';
                default:
                    return `Unable to access the camera (${error.name || 'error'}): ${error.message || 'unknown reason'}`;
            }
        },

        stop() {
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }
            this.streaming = false;
        },

        reset() {
            this.result = null;
            this.error = '';
        },

        async punch() {
            this.error = '';
            this.result = null;

            if (!this.staffId || !this.password) {
                this.error = 'Enter your staff ID and password.';
                return;
            }

            if (!this.streaming) {
                this.error = 'Start the camera first.';
                return;
            }

            this.busy = true;

            try {
                const descriptor = await detectDescriptor(this.$refs.video);

                if (!descriptor) {
                    this.error = 'No face detected. Look at the camera and try again.';
                    return;
                }

                const image = captureSnapshot(this.$refs.video, this.$refs.canvas);

                const response = await fetch(punchUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        staff_id: this.staffId,
                        password: this.password,
                        descriptor,
                        image,
                    }),
                });

                const data = await response.json();

                if (response.ok && data.ok) {
                    this.result = {
                        ok: true,
                        name: data.name,
                        photo: data.photo,
                        type: data.type,
                        time: data.time,
                        message: data.message,
                    };
                    this.staffId = '';
                    this.password = '';
                } else {
                    this.result = { ok: false, message: data.message || 'Verification failed.' };
                }
            } catch (e) {
                this.error = 'Connection error. Please try again.';
            } finally {
                this.busy = false;
            }
        },
    };
}
