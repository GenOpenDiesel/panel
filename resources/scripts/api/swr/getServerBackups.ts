import useSWR from 'swr';
import http, { getPaginationSet, PaginatedResult } from '@/api/http';
import { ServerBackup } from '@/api/server/types';
import { rawDataToServerBackup } from '@/api/transformers';
import { ServerContext } from '@/state/server';
import { createContext, useContext } from 'react';

interface ctx {
    page: number;
    setPage: (value: number | ((s: number) => number)) => void;
    includeFailed: boolean;
    setIncludeFailed: (value: boolean | ((s: boolean) => boolean)) => void;
}

export const Context = createContext<ctx>({
    page: 1,
    setPage: () => 1,
    includeFailed: false,
    setIncludeFailed: () => false,
});

type BackupResponse = PaginatedResult<ServerBackup> & { backupCount: number };

export default () => {
    const { page, includeFailed } = useContext(Context);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return useSWR<BackupResponse>(['server:backups', uuid, page, includeFailed], async () => {
        const { data } = await http.get(`/api/client/servers/${uuid}/backups`, {
            params: { page, include_failed: includeFailed ? 1 : 0 },
        });

        return {
            items: (data.data || []).map(rawDataToServerBackup),
            pagination: getPaginationSet(data.meta.pagination),
            backupCount: data.meta.backup_count,
        };
    });
};
