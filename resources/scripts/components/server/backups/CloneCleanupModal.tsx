import React, { useEffect, useMemo, useState } from 'react';
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
import { matchPluginPatterns, parsePluginPatterns } from '@/lib/matchPluginPatterns';

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
    const [patternInput, setPatternInput] = useState('');
    const { clearFlashes, clearAndAddHttpError } = useFlash();

    const patternMatches = useMemo(() => matchPluginPatterns(plugins, patternInput), [plugins, patternInput]);
    const parsedPatterns = useMemo(() => parsePluginPatterns(patternInput), [patternInput]);

    const loadPlugins = () => {
        setLoading(true);
        clearFlashes('clone-cleanup');
        getCloneCleanupPlugins(uuid)
            .then((items) => {
                setPlugins(items);
                setSelected([]);
                setPatternInput('');
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

    const onPatternInputChange = (value: string) => {
        setPatternInput(value);
        setSelected(matchPluginPatterns(plugins, value));
    };

    const onConfirm = () => {
        setLoading(true);
        clearFlashes('clone-cleanup');
        applyCloneCleanup(uuid, selected, parsedPatterns.length > 0 ? parsedPatterns : undefined)
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
            <h2 css={tw`text-2xl mb-4 text-neutral-100`}>Usuń pluginy</h2>
            <p css={tw`text-neutral-300 mb-4`}>
                Backup został przywrócony na ten serwer. Zaznacz pluginy w katalogu{' '}
                <code css={tw`text-neutral-200`}>/plugins</code>, które mają zostać usunięte, albo wpisz ich nazwy
                po przecinku.
            </p>
            <div css={tw`mb-4`}>
                <label htmlFor={'clone_plugin_patterns'} css={tw`text-sm text-neutral-300 block mb-2`}>
                    Nazwy pluginów (po przecinku)
                </label>
                <Input
                    id={'clone_plugin_patterns'}
                    value={patternInput}
                    onChange={(e) => onPatternInputChange(e.target.value)}
                    placeholder={'goxy, worldedit, luckperms'}
                />
                <p css={tw`text-xs text-neutral-400 mt-2`}>
                    Dopasowanie bez rozróżniania wielkości liter. Wpis <code css={tw`text-neutral-300`}>goxy</code>{' '}
                    usunie np. <code css={tw`text-neutral-300`}>Goxy.jar</code>,{' '}
                    <code css={tw`text-neutral-300`}>goxy-1.0.jar</code>. Foldery są pomijane.
                </p>
                {parsedPatterns.length > 0 && (
                    <p css={tw`text-xs text-neutral-300 mt-2`}>
                        Dopasowano {patternMatches.length} plik{patternMatches.length === 1 ? '' : 'ów'}.
                    </p>
                )}
            </div>
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
                <p css={tw`text-neutral-400 mb-6`}>Nie znaleziono pluginów w katalogu /plugins.</p>
            )}
            <div css={tw`flex justify-end gap-3`}>
                <Button onClick={onSkip}>Pomiń</Button>
                <Button onClick={onConfirm}>Usuń zaznaczone</Button>
            </div>
        </Modal>
    );
};

export default CloneCleanupModal;
