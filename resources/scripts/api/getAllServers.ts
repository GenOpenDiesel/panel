import getServers from '@/api/getServers';
import { Server } from '@/api/server/getServer';

interface QueryParams {
    query?: string;
    type?: string;
}

export default async ({ query, type }: QueryParams = {}): Promise<Server[]> => {
    const firstPage = await getServers({ query, type, page: 1, perPage: 100 });

    if (firstPage.pagination.totalPages <= 1) {
        return firstPage.items;
    }

    const remaining = await Promise.all(
        Array.from({ length: firstPage.pagination.totalPages - 1 }, (_, index) =>
            getServers({ query, type, page: index + 2, perPage: 100 })
        )
    );

    return [...firstPage.items, ...remaining.flatMap((page) => page.items)];
};
