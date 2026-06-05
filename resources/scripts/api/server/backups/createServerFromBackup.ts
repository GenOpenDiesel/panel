import http from '@/api/http';

export interface CreateServerFromBackupResponse {
    uuid: string;
    identifier: string;
}

export default (
    uuid: string,
    backup: string,
    name?: string,
    nodeId?: number,
    pluginTemplate?: string
): Promise<CreateServerFromBackupResponse> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/backups/${backup}/create-server`, {
            name,
            node_id: nodeId,
            plugin_template: pluginTemplate,
        })
            .then(({ data }) =>
                resolve({
                    uuid: data.attributes.uuid,
                    identifier: data.attributes.identifier,
                })
            )
            .catch(reject);
    });
};
