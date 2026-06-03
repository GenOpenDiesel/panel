import React, { useEffect, useState } from 'react';
import getAllServers from '@/api/getAllServers';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import Switch from '@/components/elements/Switch';
import tw from 'twin.macro';
import useSWR from 'swr';
import { Server } from '@/api/server/getServer';
import DashboardToolbar from '@/components/dashboard/DashboardToolbar';
import DashboardServerList from '@/components/dashboard/DashboardServerList';
import useDashboardLayout from '@/components/dashboard/useDashboardLayout';
import { createDashboardSection, DashboardSortMode, syncLayoutWithServers } from '@/lib/dashboardLayout';

export default () => {
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const [showOnlyAdmin, setShowOnlyAdmin] = usePersistedState(`${uuid}:show_all_servers`, false);
    const [isOrganizing, setIsOrganizing] = useState(false);
    const { layout, setLayout, flushLayout, isLoading: isLayoutLoading } = useDashboardLayout();

    const { data: servers, error } = useSWR<Server[]>(
        ['/api/client/servers/all', showOnlyAdmin && rootAdmin],
        () => getAllServers({ type: showOnlyAdmin && rootAdmin ? 'admin' : undefined })
    );

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'dashboard', error });
        if (!error) clearFlashes('dashboard');
    }, [error]);

    const isLoading = !servers || isLayoutLoading;

    return (
        <PageContentBlock title={'Dashboard'} showFlashKey={'dashboard'}>
            {rootAdmin && (
                <div css={tw`mb-2 flex justify-end items-center`}>
                    <p css={tw`uppercase text-xs text-neutral-400 mr-2`}>
                        {showOnlyAdmin ? "Showing others' servers" : 'Showing your servers'}
                    </p>
                    <Switch
                        name={'show_all_servers'}
                        defaultChecked={showOnlyAdmin}
                        onChange={() => setShowOnlyAdmin((s) => !s)}
                    />
                </div>
            )}

            {!isLoading && servers && servers.length > 0 && (
                <DashboardToolbar
                    sortMode={layout.sortMode}
                    isOrganizing={isOrganizing}
                    onSortModeChange={(sortMode: DashboardSortMode) => setLayout((current) => ({ ...current, sortMode }))}
                    onToggleOrganizing={() => {
                        setIsOrganizing((current) => {
                            if (current && servers) {
                                const syncedLayout = syncLayoutWithServers(layout, servers);
                                void flushLayout(syncedLayout, true).then((saved) => {
                                    if (!saved) {
                                        clearAndAddHttpError({
                                            key: 'dashboard',
                                            error: new Error('Nie udało się zapisać organizacji serwerów.'),
                                        });
                                    } else {
                                        clearFlashes('dashboard');
                                    }
                                });
                            }

                            return !current;
                        });
                    }}
                    onCreateSection={(name) =>
                        setLayout((current) => ({
                            ...current,
                            sections: [...current.sections, createDashboardSection(name)],
                        }))
                    }
                />
            )}

            {isLoading ? (
                <Spinner centered size={'large'} />
            ) : servers && servers.length > 0 ? (
                <DashboardServerList
                    servers={servers}
                    layout={layout}
                    isOrganizing={isOrganizing}
                    onLayoutChange={setLayout}
                />
            ) : (
                <p css={tw`text-center text-sm text-neutral-400`}>
                    {showOnlyAdmin
                        ? 'There are no other servers to display.'
                        : 'There are no servers associated with your account.'}
                </p>
            )}
        </PageContentBlock>
    );
};
