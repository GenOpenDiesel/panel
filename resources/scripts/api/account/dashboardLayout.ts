import http from '@/api/http';
import { DashboardLayout, DEFAULT_DASHBOARD_LAYOUT } from '@/lib/dashboardLayout';

interface DashboardLayoutResponse {
    object: string;
    attributes: DashboardLayout;
}

export const getDashboardLayout = (): Promise<DashboardLayout> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/account/dashboard-layout')
            .then(({ data }) => resolve((data as DashboardLayoutResponse).attributes ?? DEFAULT_DASHBOARD_LAYOUT))
            .catch(reject);
    });
};

export const updateDashboardLayout = (layout: DashboardLayout): Promise<DashboardLayout> => {
    return new Promise((resolve, reject) => {
        http.put('/api/client/account/dashboard-layout', { layout })
            .then(({ data }) => resolve((data as DashboardLayoutResponse).attributes ?? layout))
            .catch(reject);
    });
};
