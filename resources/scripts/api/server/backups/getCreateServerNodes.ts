import http from '@/api/http';

export interface CreateServerNodeUsage {
    id: number;
    name: string;
    location: string;
    maintenance_mode: boolean;
    online: boolean;
    servers_count: number;
    free_allocations: number;
    available_memory_mib: number;
    available_disk_mib: number;
    can_deploy: boolean;
    live: {
        memory_bytes: number;
        memory_percent: number;
        disk_bytes: number;
        disk_percent: number;
        cpu_absolute: number;
        running_servers: number;
    };
    allocated: {
        memory_mib: number;
        memory_percent: number;
        memory_max_mib: number;
        disk_mib: number;
        disk_percent: number;
        disk_max_mib: number;
    };
    system: {
        memory_bytes: number;
        cpu_threads: number;
    };
}

export interface CreateServerNodesResponse {
    cached_seconds: number;
    updated_at: string;
    nodes: CreateServerNodeUsage[];
    plugin_template: string;
}

export default (uuid: string): Promise<CreateServerNodesResponse> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/backups/create-server/nodes`)
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};
