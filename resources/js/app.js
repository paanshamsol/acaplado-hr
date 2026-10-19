import Alpine from 'alpinejs';
import { attendanceBoard, companyLocationMap, companyLogo, operationalTimesForm } from './attendance';
import { attendanceChart, leaveRequestChart } from './charts';
import { clockPunch, faceEnroll } from './face';
import { approvalItem, pendingApprovals, registerLeaveStores } from './leave-approvals';

window.Alpine = Alpine;
window.faceEnroll = faceEnroll;
window.clockPunch = clockPunch;
window.attendanceBoard = attendanceBoard;
window.operationalTimesForm = operationalTimesForm;
window.companyLocationMap = companyLocationMap;
window.companyLogo = companyLogo;
window.attendanceChart = attendanceChart;
window.leaveRequestChart = leaveRequestChart;
window.pendingApprovals = pendingApprovals;
window.approvalItem = approvalItem;

registerLeaveStores(Alpine);

Alpine.start();
