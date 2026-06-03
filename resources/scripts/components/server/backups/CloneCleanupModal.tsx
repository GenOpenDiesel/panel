import React, { useEffect, useState } from 'react';
import { ServerContext } from '@/state/server';
import Modal from '@/components/elements/Modal';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import Input from '@/components/elements/Input';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import { applyCloneCleanup, ClonePlugin, getCloneCleanupPlugins } from '@/api/server/cloneCleanup';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import { SocketEvent } from '@/components/server/events';

const CloneCleanupModal = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const cloneCleanupPending = ServerContext.useStoreState((state) => state.server.data!.cloneCleanupPending);
    const status = ServerContext.useStoreState((state) => state.server.data?.status ?? null);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const setServerFromState = ServerContext.useStoreActions((actions) => actions.server.setServerFromState);

    const [visible, setVisible] = useState(false);
    const [loading, setLoading] = useState(false);
    const [plugins, setPlugins] = useState<ClonePlugin[]>([]);
    const [selected, setSelected] = useState<string[]>([]);
    const { clearFlashes, clearAndAddHttpError } = useFlash();

    const loadPlugins = () => {
        setLoading(true);
        clearFlashes('clone-cleanup');
        getCloneCleanupPlugins(uuid)
            .then((items) => {
                setPlugins(items);
                setSelected([]);
                setVisible(true);
            })
            .catch((error) => clearAndAddHttpError({ key: 'clone-cleanup', error }))
            .then(() => setLoading(false));
    };

    useEffect(() => {
        if (cloneCleanupPending && status === null) {
            loadPlugins();
        }
    }, [cloneCleanupPending, status]);

    useWebsocketEvent(SocketEvent.BACKUP_RESTORE_COMPLETED, () => {
        getServer(uuid)
            .then(([server]) => {
                if (server.cloneCleanupPending) {
                    loadPlugins();
                }
            })
            .catch((error) => console.error(error));
    });

    const togglePlugin = (name: string) => {
        setSelected((current) =>
            current.includes(name) ? current.filter((item) => item !== name) : [...current, name]
        );
    };

    const onConfirm = () => {
        setLoading(true);
        clearFlashes('clone-cleanup');
        applyCloneCleanup(uuid, selected)
            .then(() => {
                setVisible(false);
                setServerFromState((s) => ({ ...s, cloneCleanupPending: false }));
            })
            .catch((error) => clearAndAddHttpError({ key: 'clone-cleanup', error }))
            .then(() => setLoading(false));
    };

    const onSkip = () => {
        setLoading(true);
        clearFlashes('clone-cleanup');
        applyCloneCleanup(uuid, [])
            .then(() => {
                setVisible(false);
                setServerFromState((s) => ({ ...s, cloneCleanupPending: false }));
            })
            .catch((error) => clearAndAddHttpError({ key: 'clone-cleanup', error }))
            .then(() => setLoading(false));
    };

    return (
        <Modal visible={visible} onDismissed={() => setVisible(false)} closeOnBackground={false} showSpinnerOverlay={loading}>
            <FlashMessageRender key={'clone-cleanup'} css={tw`mb-4`} />
            <h2 css={tw`text-2xl mb-4 text-neutral-100`}>Remove Plugins</h2>
            <p css={tw`text-neutral-300 mb-4`}>
                The backup has been successfully restored to this server. Select any plugins in the{' '}
                <code css={tw`text-neutral-200`}>/plugins</code> directory that should be removed before use.
            </p>
            {plugins.length > 0 ? (
                <div css={tw`space-y-2 max-h-96 overflow-y-auto mb-6`}>
                    {plugins.map((plugin) => (
                        <label
                            key={plugin.name}
                            htmlFor={`plugin_${plugin.name}`}
                            css={tw`flex items-center cursor-pointer bg-neutral-700 rounded p-3`}
                        >
                            <Input
                                id={`plugin_${plugin.name}`}
                                type={'checkbox'}
                                css={tw`w-5! h-5! mr-3`}
                                checked={selected.includes(plugin.name)}
                                onChange={() => togglePlugin(plugin.name)}
                            />
                            <span css={tw`text-neutral-100`}>{plugin.name}</span>
                            <span css={tw`ml-2 text-xs text-neutral-400`}>
                                {plugin.type === 'directory' ? '(directory)' : '(file)'}
                            </span>
                        </label>
                    ))}
                </div>
            ) : (
                <p css={tw`text-neutral-400 mb-6`}>No plugins were found in the /plugins directory.</p>
            )}
            <div css={tw`flex justify-end gap-3`}>
                <Button onClick={onSkip}>Skip</Button>
                <Button onClick={onConfirm}>Remove Selected</Button>
            </div>
        </Modal>
    );
};

export default CloneCleanupModal;
