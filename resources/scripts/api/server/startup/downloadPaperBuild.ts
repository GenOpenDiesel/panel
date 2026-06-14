import http from '@/api/http';

export interface DownloadPaperBuildResponse {
    version: string;
    build: number;
    sourceFile: string;
    filename: string;
}

export const downloadPaperBuild = (uuid: string, version: string): Promise<DownloadPaperBuildResponse> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/startup/paper/download`, { version })
            .then(({ data }) =>
                resolve({
                    version: data.version,
                    build: data.build,
                    sourceFile: data.source_file,
                    filename: data.filename,
                })
            )
            .catch(reject);
    });
};
