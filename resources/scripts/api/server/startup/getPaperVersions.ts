import http from '@/api/http';

export interface PaperVersionsResponse {
    versions: string[];
}

export const getPaperVersions = (uuid: string): Promise<PaperVersionsResponse> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/startup/paper/versions`)
            .then(({ data }) =>
                resolve({
                    versions: data.versions || [],
                })
            )
            .catch(reject);
    });
};
