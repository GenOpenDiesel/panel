import { useCallback, useEffect, useRef, useState } from 'react';
import useSWR from 'swr';
import { getDashboardLayout, updateDashboardLayout } from '@/api/account/dashboardLayout';
import { useUserSWRKey } from '@/plugins/useSWRKey';
import {
    DashboardLayout,
    DashboardLayoutScope,
    DEFAULT_SCOPED_DASHBOARD_LAYOUTS,
    normalizeScopedLayouts,
    ScopedDashboardLayouts,
} from '@/lib/dashboardLayout';

export default (scope: DashboardLayoutScope) => {
    const swrKey = useUserSWRKey(['account', 'dashboard-layout']);
    const saveTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);
    const pendingLayoutRef = useRef<ScopedDashboardLayouts | null>(null);
    const scopedLayoutsRef = useRef<ScopedDashboardLayouts>(DEFAULT_SCOPED_DASHBOARD_LAYOUTS);
    const dirtyRef = useRef(false);
    const isSavingRef = useRef(false);
    const savePromiseRef = useRef<Promise<boolean> | null>(null);
    const [scopedLayouts, setScopedLayouts] = useState<ScopedDashboardLayouts>(DEFAULT_SCOPED_DASHBOARD_LAYOUTS);

    const { data, error, mutate } = useSWR(swrKey, getDashboardLayout, {
        revalidateOnFocus: false,
    });

    scopedLayoutsRef.current = scopedLayouts;

    useEffect(() => {
        if (!data) {
            return;
        }

        if (!dirtyRef.current) {
            const normalized = normalizeScopedLayouts(data);
            setScopedLayouts(normalized);
            scopedLayoutsRef.current = normalized;
        }
    }, [data]);

    const flushLayout = useCallback(
        async (layoutToSave?: ScopedDashboardLayouts, force = false): Promise<boolean> => {
            if (isSavingRef.current && savePromiseRef.current) {
                await savePromiseRef.current;
            }

            const nextLayout = layoutToSave ?? pendingLayoutRef.current ?? scopedLayoutsRef.current;
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
                    scopedLayoutsRef.current = saved;
                    setScopedLayouts(saved);
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
        (nextLayout: ScopedDashboardLayouts) => {
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
            setScopedLayouts((current) => {
                const nextScopeLayout =
                    typeof updater === 'function' ? updater(current[scope]) : updater;
                const nextLayouts = {
                    ...current,
                    [scope]: nextScopeLayout,
                };
                scopedLayoutsRef.current = nextLayouts;
                persistLayout(nextLayouts);

                return nextLayouts;
            });
        },
        [persistLayout, scope]
    );

    useEffect(() => {
        return () => {
            void flushLayout(undefined, true);
        };
    }, [flushLayout]);

    return {
        layout: scopedLayouts[scope],
        setLayout,
        flushLayout,
        isLoading: !data && !error,
        error,
    };
};
