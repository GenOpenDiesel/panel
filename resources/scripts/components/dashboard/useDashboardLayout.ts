import { useCallback, useEffect, useRef, useState } from 'react';
import useSWR from 'swr';
import { getDashboardLayout, updateDashboardLayout } from '@/api/account/dashboardLayout';
import { useUserSWRKey } from '@/plugins/useSWRKey';
import { DashboardLayout, DEFAULT_DASHBOARD_LAYOUT } from '@/lib/dashboardLayout';

export default () => {
    const swrKey = useUserSWRKey(['account', 'dashboard-layout']);
    const saveTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingLayoutRef = useRef<DashboardLayout | null>(null);
    const layoutRef = useRef<DashboardLayout>(DEFAULT_DASHBOARD_LAYOUT);
    const dirtyRef = useRef(false);
    const isSavingRef = useRef(false);
    const savePromiseRef = useRef<Promise<boolean> | null>(null);
    const [layout, setLayoutState] = useState<DashboardLayout>(DEFAULT_DASHBOARD_LAYOUT);

    const { data, error, mutate } = useSWR(swrKey, getDashboardLayout, {
        revalidateOnFocus: false,
    });

    layoutRef.current = layout;

    useEffect(() => {
        if (!data) {
            return;
        }

        if (!dirtyRef.current) {
            setLayoutState(data);
            layoutRef.current = data;
        }
    }, [data]);

    const flushLayout = useCallback(
        async (layoutToSave?: DashboardLayout, force = false): Promise<boolean> => {
            if (isSavingRef.current && savePromiseRef.current) {
                await savePromiseRef.current;
            }

            const nextLayout = layoutToSave ?? pendingLayoutRef.current ?? layoutRef.current;
            if (!nextLayout) {
                return true;
            }

            if (!force && !dirtyRef.current) {
                return true;
            }

            if (saveTimeout.current) {
                clearTimeout(saveTimeout.current);
                saveTimeout.current = null;
            }

            const saveOperation = (async () => {
                isSavingRef.current = true;

                try {
                    const saved = await updateDashboardLayout(nextLayout);
                    pendingLayoutRef.current = null;
                    dirtyRef.current = false;
                    layoutRef.current = saved;
                    setLayoutState(saved);
                    mutate(saved, false);

                    return true;
                } catch {
                    dirtyRef.current = true;
                    pendingLayoutRef.current = nextLayout;
                    mutate();

                    return false;
                } finally {
                    isSavingRef.current = false;
                    savePromiseRef.current = null;
                }
            })();

            savePromiseRef.current = saveOperation;

            return saveOperation;
        },
        [mutate]
    );

    const persistLayout = useCallback(
        (nextLayout: DashboardLayout) => {
            pendingLayoutRef.current = nextLayout;
            dirtyRef.current = true;

            if (saveTimeout.current) {
                clearTimeout(saveTimeout.current);
            }

            saveTimeout.current = setTimeout(() => {
                void flushLayout(nextLayout);
            }, 400);
        },
        [flushLayout]
    );

    const setLayout = useCallback(
        (updater: DashboardLayout | ((current: DashboardLayout) => DashboardLayout)) => {
            setLayoutState((current) => {
                const nextLayout = typeof updater === 'function' ? updater(current) : updater;
                layoutRef.current = nextLayout;
                persistLayout(nextLayout);

                return nextLayout;
            });
        },
        [persistLayout]
    );

    useEffect(() => {
        return () => {
            void flushLayout(undefined, true);
        };
    }, [flushLayout]);

    return {
        layout,
        setLayout,
        flushLayout,
        isLoading: !data && !error,
        error,
    };
};
