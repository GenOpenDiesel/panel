import http from '@/api/http';

export interface FileSearchMatch {
    file: string;
    line: number;
    content: string;
}

export interface FileSearchMeta {
    files_scanned: number;
    files_skipped_size: number;
    files_skipped_extension: number;
    directories_skipped: number;
    directories_scanned: number;
    errors: number;
    matches: number;
    truncated: boolean;
}

export interface FileSearchProgress {
    done: boolean;
    percent: number;
    files_scanned: number;
    files_limit: number;
    directories_scanned: number;
    directories_pending: number;
    matches: number;
    current_phase: 'scanning_files' | 'listing_directories';
}

export interface FileSearchResponse {
    query: string;
    results: FileSearchMatch[];
    meta: FileSearchMeta;
    progress: FileSearchProgress;
    search_id: string | null;
}

export const searchFileContent = (
    uuid: string,
    query: string,
    directory?: string,
    searchId?: string | null
): Promise<FileSearchResponse> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/files/search`, {
            query,
            directory: directory ?? '/',
            search_id: searchId ?? null,
        })
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};

export default searchFileContent;

export const searchFileContentAll = async (
    uuid: string,
    query: string,
    directory?: string,
    onProgress?: (progress: FileSearchProgress, partialResults: FileSearchMatch[]) => void
): Promise<FileSearchResponse> => {
    let searchId: string | null = null;
    let aggregated: FileSearchResponse | null = null;

    do {
        const chunk = await searchFileContent(uuid, query, directory, searchId);
        aggregated = aggregated
            ? {
                  ...chunk,
                  results: [...aggregated.results, ...chunk.results],
              }
            : chunk;

        onProgress?.(chunk.progress, aggregated.results);
        searchId = chunk.search_id;
    } while (searchId);

    return aggregated!;
};
