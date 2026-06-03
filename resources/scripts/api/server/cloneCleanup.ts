import http from '@/api/http';

export interface ClonePlugin {
    name: string;
    type: 'file' | 'directory';
}

export const getCloneCleanupPlugins = (uuid: string): Promise<ClonePlugin[]> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/clone-cleanup/plugins`)
            .then(({ data }) => resolve(data.plugins || []))
            .catch(reject);
    });
};

export const applyCloneCleanup = (uuid: string, plugins: string[]): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/clone-cleanup`, { plugins })
            .then(() => resolve())
            .catch(reject);
    });
};
