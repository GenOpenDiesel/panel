import http from '@/api/http';

export interface ClonePlugin {
    name: string;
    type: 'file' | 'directory';
}

export interface CloneCleanupPluginsResponse {
    plugins: ClonePlugin[];
    template: string;
}

export const getCloneCleanupPlugins = (uuid: string): Promise<CloneCleanupPluginsResponse> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/clone-cleanup/plugins`)
            .then(({ data }) =>
                resolve({
                    plugins: data.plugins || [],
                    template: data.template || '',
                })
            )
            .catch(reject);
    });
};

export const applyCloneCleanup = (uuid: string, plugins: string[], patterns?: string[]): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/clone-cleanup`, { plugins, patterns })
            .then(() => resolve())
            .catch(reject);
    });
};
