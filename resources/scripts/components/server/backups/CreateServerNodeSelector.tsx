import React, { useEffect, useRef, useState, useCallback } from 'react';
import tw from 'twin.macro';
import Spinner from '@/components/elements/Spinner';
import getCreateServerNodes, { CreateServerNodeUsage } from '@/api/server/backups/getCreateServerNodes';

interface Props {
    serverUuid: string;
    selectedNodeId: number | null;
    memoryMiB: number;
    onSelect: (nodeId: number | null) => void;
}

const formatBytes = (bytes: number): string => {
    if (!bytes || bytes <= 0) return '0 B';
    const units = ['B', 'KiB', 'MiB', 'GiB'];
    let value = bytes;
    let i = 0;
    while (value >= 1024 && i < units.length - 1) {
        value /= 1024;
        i++;
    }
    return `${value.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
};

const formatMib = (mib: number): string => {
    if (!mib || mib <= 0) return '0 MiB';
    if (mib >= 1024) return `${(mib / 1024).toFixed(1)} GiB`;
    return `${mib.toLocaleString()} MiB`;
};

const barColor = (percent: number) => {
    if (percent <= 60) return 'bg-green-500';
    if (percent <= 85) return 'bg-yellow-500';
    return 'bg-red-500';
};

const UsageBar = ({ percent, label }: { percent: number; label: string }) => (
    <div css={tw`mb-1`}>
        <div css={tw`flex justify-between text-xs text-neutral-300 mb-0.5`}>
            <span>{label}</span>
            <span>{Math.min(100, Math.max(0, percent)).toFixed(1)}%</span>
        </div>
        <div css={tw`h-1.5 bg-neutral-800 rounded overflow-hidden`}>
            <div
                className={barColor(percent)}
                css={tw`h-full rounded transition-all duration-300`}
                style={{ width: `${Math.min(100, Math.max(0, percent))}%` }}
            />
        </div>
    </div>
);

export default ({ serverUuid, selectedNodeId, memoryMiB, onSelect }: Props) => {
    const [nodes, setNodes] = useState<CreateServerNodeUsage[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);
    const [updatedAt, setUpdatedAt] = useState<string | null>(null);
    const timerRef = useRef<number | null>(null);
    const refreshMsRef = useRef(15000);
    const initialSelectionDone = useRef(false);
    const selectedNodeIdRef = useRef(selectedNodeId);
    selectedNodeIdRef.current = selectedNodeId;

    const loadNodes = useCallback(() => {
        getCreateServerNodes(serverUuid, memoryMiB)
            .then((data) => {
                setNodes(data.nodes);
                setUpdatedAt(data.updated_at);
                refreshMsRef.current = (data.cached_seconds || 15) * 1000;
                setError(false);

                if (!initialSelectionDone.current) {
                    initialSelectionDone.current = true;
                    const preferred = data.nodes.find((n) => n.can_deploy);
                    if (preferred) {
                        onSelect(preferred.id);
                    }
                } else if (selectedNodeIdRef.current !== null) {
                    const current = data.nodes.find((n) => n.id === selectedNodeIdRef.current);
                    if (current && !current.can_deploy) {
                        onSelect(data.nodes.find((n) => n.can_deploy)?.id ?? null);
                    }
                }
            })
            .catch(() => setError(true))
            .finally(() => setLoading(false));
    }, [serverUuid, memoryMiB, onSelect]);

    useEffect(() => {
        initialSelectionDone.current = false;
        setLoading(true);
        loadNodes();

        const schedule = () => {
            if (timerRef.current) window.clearTimeout(timerRef.current);
            timerRef.current = window.setTimeout(() => {
                loadNodes();
                schedule();
            }, refreshMsRef.current);
        };

        schedule();

        return () => {
            if (timerRef.current) window.clearTimeout(timerRef.current);
        };
    }, [serverUuid, memoryMiB, loadNodes]);

    if (loading && nodes.length === 0) {
        return (
            <div css={tw`flex justify-center py-6`}>
                <Spinner size={'large'} />
            </div>
        );
    }

    if (error && nodes.length === 0) {
        return <p css={tw`text-red-400 text-sm`}>Nie udało się pobrać listy węzłów.</p>;
    }

    return (
        <div>
            <div css={tw`flex items-center justify-between mb-2`}>
                <label css={tw`text-base font-medium`}>Węzeł docelowy</label>
                {updatedAt && (
                    <span css={tw`text-xs text-neutral-400`}>
                        Odświeżono: {new Date(updatedAt).toLocaleTimeString()}
                    </span>
                )}
            </div>
            <div css={tw`space-y-2 max-h-64 overflow-y-auto pr-1`}>
                {nodes.map((node) => {
                    const selected = selectedNodeId === node.id;
                    const disabled = !node.can_deploy;

                    return (
                        <button
                            key={node.id}
                            type={'button'}
                            disabled={disabled}
                            onClick={() => onSelect(node.id)}
                            css={[
                                tw`w-full text-left p-3 rounded border transition-colors duration-150`,
                                selected
                                    ? tw`border-cyan-500 bg-cyan-900/20`
                                    : tw`border-neutral-600 bg-neutral-700 hover:border-neutral-500`,
                                disabled && tw`opacity-50 cursor-not-allowed hover:border-neutral-600`,
                            ]}
                        >
                            <div css={tw`flex items-center justify-between mb-2`}>
                                <span css={tw`font-medium text-sm`}>
                                    {node.online ? (
                                        <span css={tw`text-green-400 mr-1.5`} title={'Online'}>
                                            ●
                                        </span>
                                    ) : (
                                        <span css={tw`text-red-400 mr-1.5`} title={'Offline'}>
                                            ●
                                        </span>
                                    )}
                                    {node.name}
                                </span>
                                <span css={tw`text-xs text-neutral-400`}>{node.location}</span>
                            </div>
                            {node.maintenance_mode && <p css={tw`text-xs text-yellow-400 mb-1`}>Tryb konserwacji</p>}
                            {!node.online ? (
                                <p css={tw`text-xs text-neutral-400`}>Węzeł niedostępny</p>
                            ) : (
                                <>
                                    <UsageBar
                                        percent={node.live.memory_percent}
                                        label={`RAM: ${formatBytes(node.live.memory_bytes)} / ${formatBytes(
                                            node.system.memory_bytes
                                        )}`}
                                    />
                                    <UsageBar
                                        percent={node.live.cpu_absolute}
                                        label={`CPU: ${node.live.cpu_absolute.toFixed(1)}% (${
                                            node.system.cpu_threads
                                        } wątków)`}
                                    />
                                    <UsageBar
                                        percent={node.live.disk_percent}
                                        label={`Dysk: ${formatBytes(node.live.disk_bytes)} / ${formatMib(
                                            node.allocated.disk_max_mib
                                        )}`}
                                    />
                                    <UsageBar
                                        percent={node.allocated.memory_percent}
                                        label={`Alokacja RAM: ${formatMib(node.allocated.memory_mib)} / ${formatMib(
                                            node.allocated.memory_max_mib
                                        )}`}
                                    />
                                </>
                            )}
                            <p css={tw`text-xs text-neutral-400 mt-1`}>
                                {node.live.running_servers} uruchomionych / {node.servers_count} serwerów ·{' '}
                                {node.free_allocations} wolnych portów
                                {!node.can_deploy && node.online && !node.maintenance_mode && (
                                    <span css={tw`text-red-400`}> · Brak wolnych zasobów</span>
                                )}
                            </p>
                        </button>
                    );
                })}
            </div>
            {nodes.every((n) => !n.can_deploy) && (
                <p css={tw`text-yellow-400 text-sm mt-2`}>
                    Żaden węzeł nie ma wystarczających zasobów do utworzenia serwera.
                </p>
            )}
        </div>
    );
};
