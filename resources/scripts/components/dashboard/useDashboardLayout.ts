import { useCallback, useEffect, useRef, useState } from 'react';
import useSWR from 'swr';
import { getDashboardLayout, updateDashboardLayout } from '@/api/account/dashboardLayout';
import { useUserSWRKey } from '@/plugins/useSWRKey';
import {
    cloneDashboardLayout,
    cloneScopedDashboardLayouts,
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

    const resolveLayoutToSave = useCallback(
        (layoutToSave?: ScopedDashboardLayouts | DashboardLayout): ScopedDashboardLayouts => {
            if (!layoutToSave) {
                return cloneScopedDashboardLayouts(pendingLayoutRef.current ?? scopedLayoutsRef.current);
            }

            if ('own' in layoutToSave && 'admin' in layoutToSave) {
                return cloneScopedDashboardLayouts(layoutToSave as ScopedDashboardLayouts);
            }

            return cloneScopedDashboardLayouts({
                ...scopedLayoutsRef.current,
                [scope]: layoutToSave,
            });
        },
        [scope]
    );

    const flushLayout = useCallback(
        async (layoutToSave?: ScopedDashboardLayouts | DashboardLayout, force = false): Promise<boolean> => {
            if (isSavingRef.current && savePromiseRef.current) {
                await savePromiseRef.current;
            }

            const nextLayout = resolveLayoutToSave(layoutToSave);
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
                    const normalized = normalizeScopedLayouts(saved);
                    pendingLayoutRef.current = null;
                    dirtyRef.current = false;
                    scopedLayoutsRef.current = normalized;
                    setScopedLayouts(normalized);
                    mutate(normalized, false);

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
        [mutate, resolveLayoutToSave]
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
                const currentScopeLayout = cloneDashboardLayout(current[scope]);
                const nextScopeLayout = typeof updater === 'function' ? updater(currentScopeLayout) : updater;
                const nextLayouts = cloneScopedDashboardLayouts({
                    ...current,
                    [scope]: nextScopeLayout,
                });
                scopedLayoutsRef.current = nextLayouts;
                persistLayout(nextLayouts);

                return nextLayouts;
            });
        },
        [persistLayout, scope]
    );

    const flushLayoutRef = useRef(flushLayout);
    flushLayoutRef.current = flushLayout;

    useEffect(() => {
        return () => {
            void flushLayoutRef.current(undefined, true);
        };
    }, []);

    return {
        layout: scopedLayouts[scope],
        setLayout,
        flushLayout,
        isLoading: !data && !error,
        error,
    };
};
