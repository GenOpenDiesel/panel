import React, { useMemo, useRef, useState } from 'react';
import tw from 'twin.macro';
import { Server } from '@/api/server/getServer';
import ServerRow from '@/components/dashboard/ServerRow';
import {
    DashboardLayout,
    moveServerInLayout,
    organizeDashboardServers,
    removeDashboardSection,
    renameDashboardSection,
    reorderSectionServers,
    reorderUnsectionedServers,
} from '@/lib/dashboardLayout';
import { Button } from '@/components/elements/button/index';
import Input from '@/components/elements/Input';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faGripVertical } from '@fortawesome/free-solid-svg-icons';

interface DragPayload {
    serverUuid: string;
    sectionId: string | null;
}

const DRAG_MIME = 'application/x-pterodactyl-dashboard-server';
const dragPayloadRef: { current: DragPayload | null } = { current: null };

interface Props {
    servers: Server[];
    layout: DashboardLayout;
    isOrganizing: boolean;
    onLayoutChange: (layout: DashboardLayout) => void;
}

const readDragPayload = (event: React.DragEvent): DragPayload | null => {
    if (dragPayloadRef.current) {
        return dragPayloadRef.current;
    }

    const raw = event.dataTransfer.getData(DRAG_MIME) || event.dataTransfer.getData('text/plain');
    if (!raw) {
        return null;
    }

    try {
        return JSON.parse(raw) as DragPayload;
    } catch {
        return null;
    }
};

const clearDragPayload = () => {
    dragPayloadRef.current = null;
};

const SectionHeader = ({
    name,
    isOrganizing,
    onRename,
    onDelete,
}: {
    name: string;
    isOrganizing: boolean;
    onRename: (name: string) => void;
    onDelete: () => void;
}) => {
    const [editing, setEditing] = useState(false);
    const [value, setValue] = useState(name);

    const commit = () => {
        const next = value.trim();
        if (next && next !== name) {
            onRename(next);
        } else {
            setValue(name);
        }

        setEditing(false);
    };

    return (
        <div css={tw`mb-2 flex items-center justify-between gap-3`}>
            {editing ? (
                <Input
                    autoFocus
                    value={value}
                    onChange={(event) => setValue(event.currentTarget.value)}
                    onBlur={commit}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            commit();
                        }
                    }}
                />
            ) : (
                <h3
                    css={tw`text-sm uppercase tracking-wide text-neutral-300 cursor-pointer`}
                    onClick={() => isOrganizing && setEditing(true)}
                >
                    {name}
                </h3>
            )}
            {isOrganizing && (
                <Button type={'button'} variant={Button.Variants.Secondary} size={Button.Sizes.Small} onClick={onDelete}>
                    Usuń sekcję
                </Button>
            )}
        </div>
    );
};

const DraggableServerRow = ({
    server,
    sectionId,
    index,
    isOrganizing,
    onDropAt,
}: {
    server: Server;
    sectionId: string | null;
    index: number;
    isOrganizing: boolean;
    onDropAt: (payload: DragPayload, targetIndex: number) => void;
}) => {
    const [dragOver, setDragOver] = useState(false);

    return (
        <div
            css={[tw`relative`, dragOver && isOrganizing && tw`ring-2 ring-cyan-500 rounded`]}
            onDragOver={(event) => {
                if (!isOrganizing) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                event.dataTransfer.dropEffect = 'move';
                setDragOver(true);
            }}
            onDragLeave={() => setDragOver(false)}
            onDrop={(event) => {
                if (!isOrganizing) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                setDragOver(false);

                const payload = readDragPayload(event);
                if (!payload) {
                    return;
                }

                onDropAt(payload, index);
                clearDragPayload();
            }}
        >
            {isOrganizing && (
                <div
                    css={tw`absolute left-0 top-0 bottom-0 w-8 flex items-center justify-center text-neutral-500 z-10 cursor-move`}
                    draggable
                    onDragStart={(event) => {
                        const payload = { serverUuid: server.uuid, sectionId } as DragPayload;
                        dragPayloadRef.current = payload;
                        const encoded = JSON.stringify(payload);
                        event.dataTransfer.setData(DRAG_MIME, encoded);
                        event.dataTransfer.setData('text/plain', encoded);
                        event.dataTransfer.effectAllowed = 'move';
                    }}
                    onDragEnd={clearDragPayload}
                >
                    <FontAwesomeIcon icon={faGripVertical} />
                </div>
            )}
            <ServerRow server={server} disableNavigation={isOrganizing} css={[tw`mt-2`, isOrganizing && tw`pl-8`]} />
        </div>
    );
};

const SectionDropArea = ({
    sectionId,
    isOrganizing,
    isEmpty,
    onDropAt,
    children,
}: {
    sectionId: string | null;
    isOrganizing: boolean;
    isEmpty: boolean;
    onDropAt: (payload: DragPayload, targetIndex: number) => void;
    children: React.ReactNode;
}) => {
    const [dragOver, setDragOver] = useState(false);

    return (
        <div
            css={[
                tw`rounded transition-colors duration-150`,
                isOrganizing && tw`min-h-[3rem]`,
                dragOver && isOrganizing && tw`bg-cyan-500 bg-opacity-10 ring-2 ring-cyan-500 border border-dashed border-cyan-500`,
            ]}
            onDragOver={(event) => {
                if (!isOrganizing) {
                    return;
                }

                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                setDragOver(true);
            }}
            onDragLeave={(event) => {
                if (event.currentTarget.contains(event.relatedTarget as Node)) {
                    return;
                }

                setDragOver(false);
            }}
            onDrop={(event) => {
                if (!isOrganizing) {
                    return;
                }

                event.preventDefault();
                setDragOver(false);

                const payload = readDragPayload(event);
                if (!payload) {
                    return;
                }

                onDropAt(payload, isEmpty ? 0 : Number.MAX_SAFE_INTEGER);
                clearDragPayload();
            }}
        >
            {children}
            {isOrganizing && isEmpty && (
                <div css={tw`rounded border border-dashed border-neutral-600 p-6 text-center text-sm text-neutral-500 pointer-events-none`}>
                    Przeciągnij serwery tutaj
                </div>
            )}
        </div>
    );
};

export default ({ servers, layout, isOrganizing, onLayoutChange }: Props) => {
    const layoutRef = useRef(layout);
    layoutRef.current = layout;

    const organized = useMemo(() => organizeDashboardServers(servers, layout), [servers, layout]);

    const handleDropAt = (targetSectionId: string | null, targetIndex: number, payload: DragPayload) => {
        const currentLayout = layoutRef.current;

        if (payload.sectionId === targetSectionId) {
            if (targetSectionId) {
                const section = currentLayout.sections.find((entry) => entry.id === targetSectionId);
                if (!section) {
                    return;
                }

                const currentIndex = section.serverUuids.indexOf(payload.serverUuid);
                if (currentIndex === -1) {
                    return;
                }

                const serverUuids = [...section.serverUuids];
                serverUuids.splice(currentIndex, 1);

                const insertIndex =
                    targetIndex === Number.MAX_SAFE_INTEGER
                        ? serverUuids.length
                        : currentIndex < targetIndex
                        ? Math.min(targetIndex - 1, serverUuids.length)
                        : Math.min(targetIndex, serverUuids.length);

                serverUuids.splice(insertIndex, 0, payload.serverUuid);

                onLayoutChange(
                    reorderSectionServers({ ...currentLayout, sortMode: 'custom' }, targetSectionId, serverUuids)
                );
            } else {
                const currentIndex = currentLayout.unsectionedOrder.indexOf(payload.serverUuid);
                if (currentIndex === -1) {
                    return;
                }

                const unsectionedOrder = [...currentLayout.unsectionedOrder];
                unsectionedOrder.splice(currentIndex, 1);

                const insertIndex =
                    targetIndex === Number.MAX_SAFE_INTEGER
                        ? unsectionedOrder.length
                        : currentIndex < targetIndex
                        ? Math.min(targetIndex - 1, unsectionedOrder.length)
                        : Math.min(targetIndex, unsectionedOrder.length);

                unsectionedOrder.splice(insertIndex, 0, payload.serverUuid);

                onLayoutChange(reorderUnsectionedServers({ ...currentLayout, sortMode: 'custom' }, unsectionedOrder));
            }

            return;
        }

        const insertIndex = targetIndex === Number.MAX_SAFE_INTEGER ? undefined : targetIndex;
        onLayoutChange(
            moveServerInLayout({ ...currentLayout, sortMode: 'custom' }, payload.serverUuid, targetSectionId, insertIndex)
        );
    };

    const renderServers = (sectionServers: Server[], sectionId: string | null) => (
        <SectionDropArea
            sectionId={sectionId}
            isOrganizing={isOrganizing}
            isEmpty={sectionServers.length === 0}
            onDropAt={(payload, index) => handleDropAt(sectionId, index, payload)}
        >
            {sectionServers.map((server, index) => (
                <DraggableServerRow
                    key={server.uuid}
                    server={server}
                    sectionId={sectionId}
                    index={index}
                    isOrganizing={isOrganizing}
                    onDropAt={(payload, dropIndex) => handleDropAt(sectionId, dropIndex, payload)}
                />
            ))}
        </SectionDropArea>
    );

    return (
        <div css={tw`space-y-6`}>
            {organized.sections.map(({ section, servers: sectionServers }) => (
                <section key={section.id}>
                    <SectionHeader
                        name={section.name}
                        isOrganizing={isOrganizing}
                        onRename={(name) => onLayoutChange(renameDashboardSection(layout, section.id, name))}
                        onDelete={() => onLayoutChange(removeDashboardSection(layout, section.id))}
                    />
                    {renderServers(sectionServers, section.id)}
                </section>
            ))}

            {(organized.unsectioned.length > 0 || organized.sections.length === 0) && (
                <section>
                    {organized.sections.length > 0 && (
                        <h3 css={tw`mb-2 text-sm uppercase tracking-wide text-neutral-400`}>Pozostałe serwery</h3>
                    )}
                    {renderServers(organized.unsectioned, null)}
                </section>
            )}
        </div>
    );
};
