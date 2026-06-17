import http from '@/api/http';

export default async (serverUuid: string, backupUuid: string): Promise<string> => {
    const { data } = await http.post(`/api/client/servers/${serverUuid}/backups/${backupUuid}/create-instance`);
    return data.server_uuid;
};
