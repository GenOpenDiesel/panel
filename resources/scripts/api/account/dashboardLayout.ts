import http from '@/api/http';
import {
    DEFAULT_SCOPED_DASHBOARD_LAYOUTS,
    normalizeScopedLayouts,
    ScopedDashboardLayouts,
} from '@/lib/dashboardLayout';

interface DashboardLayoutResponse {
    object: string;
    attributes: unknown;
}

export const getDashboardLayout = (): Promise<ScopedDashboardLayouts> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/account/dashboard-layout')
            .then(({ data }) =>
                resolve(normalizeScopedLayouts((data as DashboardLayoutResponse).attributes ?? DEFAULT_SCOPED_DASHBOARD_LAYOUTS))
            )
            .catch(reject);
    });
};

export const updateDashboardLayout = (layout: ScopedDashboardLayouts): Promise<ScopedDashboardLayouts> => {
    return new Promise((resolve, reject) => {
        http.put('/api/client/account/dashboard-layout', { layout })
            .then(({ data }) =>
                resolve(normalizeScopedLayouts((data as DashboardLayoutResponse).attributes ?? layout))
            )
            .catch(reject);
    });
};
