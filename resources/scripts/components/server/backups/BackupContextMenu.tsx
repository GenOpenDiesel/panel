import React, { useEffect, useState } from 'react';
import {
    faBoxOpen,
    faCloudDownloadAlt,
    faEllipsisH,
    faLock,
    faPlus,
    faTrashAlt,
    faUnlock,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import DropdownMenu, { DropdownButtonRow } from '@/components/elements/DropdownMenu';
import getBackupDownloadUrl from '@/api/server/backups/getBackupDownloadUrl';
import useFlash from '@/plugins/useFlash';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import deleteBackup from '@/api/server/backups/deleteBackup';
import Can from '@/components/elements/Can';
import tw from 'twin.macro';
import getServerBackups from '@/api/swr/getServerBackups';
import { ServerBackup } from '@/api/server/types';
import { ServerContext } from '@/state/server';
import Input from '@/components/elements/Input';
import { restoreServerBackup } from '@/api/server/backups';
import createServerFromBackup from '@/api/server/backups/createServerFromBackup';
import getCreateServerNodes from '@/api/server/backups/getCreateServerNodes';
import CreateServerNodeSelector from '@/components/server/backups/CreateServerNodeSelector';
import http, { httpErrorToHuman } from '@/api/http';
import { Dialog } from '@/components/elements/dialog';
import { useStoreState } from '@/state/hooks';

interface Props {
    backup: ServerBackup;
}

export default ({ backup }: Props) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const setServerFromState = ServerContext.useStoreActions((actions) => actions.server.setServerFromState);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const canDeleteBackup = !backup.isProtected || rootAdmin;
    const [modal, setModal] = useState('');
    const [loading, setLoading] = useState(false);
    const [truncate, setTruncate] = useState(false);
    const [serverName, setServerName] = useState('');
    const [selectedNodeId, setSelectedNodeId] = useState<number | null>(null);
    const [pluginTemplate, setPluginTemplate] = useState('');
    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();

    useEffect(() => {
        if (modal !== 'create-server') {
            return;
        }

        getCreateServerNodes(uuid)
            .then((data) => setPluginTemplate(data.plugin_template || ''))
            .catch((error) => console.error(error));
    }, [modal, uuid]);
    const { mutate } = getServerBackups();

    const doDownload = () => {
        setLoading(true);
        clearFlashes('backups');
        getBackupDownloadUrl(uuid, backup.uuid)
            .then((url) => {
                // @ts-expect-error this is valid
                window.location = url;
            })
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'backups', error });
            })
            .then(() => setLoading(false));
    };

    const doDeletion = () => {
        setLoading(true);
        clearFlashes('backups');
        deleteBackup(uuid, backup.uuid)
            .then(() =>
                mutate(
                    (data) => ({
                        ...data,
                        items: data.items.filter((b) => b.uuid !== backup.uuid),
                        backupCount: data.backupCount - 1,
                    }),
                    false
                )
            )
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'backups', error });
                setLoading(false);
                setModal('');
            });
    };

    const doRestorationAction = () => {
        setLoading(true);
        clearFlashes('backups');
        restoreServerBackup(uuid, backup.uuid, truncate)
            .then(() =>
                setServerFromState((s) => ({
                    ...s,
                    status: 'restoring_backup',
                }))
            )
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'backups', error });
            })
            .then(() => setLoading(false))
            .then(() => setModal(''));
    };

    const doCreateServer = () => {
        if (selectedNodeId === null) {
            clearFlashes('backups');
            addFlash({
                key: 'backups',
                type: 'error',
                message: 'Select a node where the new server should be created.',
            });
            return;
        }

        setLoading(true);
        clearFlashes('backups');
        createServerFromBackup(uuid, backup.uuid, serverName || undefined, selectedNodeId, pluginTemplate)
            .then((server) => {
                addFlash({
                    key: 'backups',
                    type: 'success',
                    message: 'A new server has been created and the backup restoration process has started.',
                });
                window.open(`/server/${server.identifier}`, '_blank');
                setModal('');
                setServerName('');
                setSelectedNodeId(null);
                setPluginTemplate('');
            })
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'backups', error });
            })
            .then(() => setLoading(false));
    };

    const onLockToggle = () => {
        if (backup.isLocked && modal !== 'unlock') {
            return setModal('unlock');
        }

        http.post(`/api/client/servers/${uuid}/backups/${backup.uuid}/lock`)
            .then(() =>
                mutate(
                    (data) => ({
                        ...data,
                        items: data.items.map((b) =>
                            b.uuid !== backup.uuid
                                ? b
                                : {
                                      ...b,
                                      isLocked: !b.isLocked,
                                  }
                        ),
                    }),
                    false
                )
            )
            .catch((error) => alert(httpErrorToHuman(error)))
            .then(() => setModal(''));
    };

    return (
        <>
            <Dialog.Confirm
                open={modal === 'unlock'}
                onClose={() => setModal('')}
                title={`Unlock "${backup.name}"`}
                onConfirmed={onLockToggle}
            >
                This backup will no longer be protected from automated or accidental deletions.
            </Dialog.Confirm>
            <Dialog.Confirm
                open={modal === 'restore'}
                onClose={() => setModal('')}
                confirm={'Restore'}
                title={`Restore "${backup.name}"`}
                onConfirmed={() => doRestorationAction()}
            >
                <p>
                    Your server will be stopped. You will not be able to control the power state, access the file
                    manager, or create additional backups until completed.
                </p>
                <p css={tw`mt-4 -mb-2 bg-gray-700 p-3 rounded`}>
                    <label htmlFor={'restore_truncate'} css={tw`text-base flex items-center cursor-pointer`}>
                        <Input
                            type={'checkbox'}
                            css={tw`text-red-500! w-5! h-5! mr-2`}
                            id={'restore_truncate'}
                            value={'true'}
                            checked={truncate}
                            onChange={() => setTruncate((s) => !s)}
                        />
                        Delete all files before restoring backup.
                    </label>
                </p>
            </Dialog.Confirm>
            <Dialog.Confirm
                open={modal === 'create-server'}
                onClose={() => {
                    setModal('');
                    setServerName('');
                    setSelectedNodeId(null);
                    setPluginTemplate('');
                }}
                confirm={'Create Server'}
                title={`Create Server from "${backup.name}"`}
                onConfirmed={doCreateServer}
            >
                <p>
                    A new server will be provisioned using the same configuration as this server, with the CPU limit
                    set to 300%. The selected backup will be restored to the new instance. After restoration completes,
                    matching plugins from the template below will be selected for removal.
                </p>
                <div css={tw`mt-4 -mb-2 bg-gray-700 p-3 rounded`}>
                    <CreateServerNodeSelector
                        serverUuid={uuid}
                        selectedNodeId={selectedNodeId}
                        onSelect={setSelectedNodeId}
                    />
                </div>
                <p css={tw`mt-4 -mb-2 bg-gray-700 p-3 rounded`}>
                    <label htmlFor={'clone_server_name'} css={tw`text-base block mb-2`}>
                        Server Name (Optional)
                    </label>
                    <Input
                        id={'clone_server_name'}
                        value={serverName}
                        onChange={(e) => setServerName(e.target.value)}
                        placeholder={`Clone: ${backup.name}`}
                    />
                </p>
                <p css={tw`mt-4 -mb-2 bg-gray-700 p-3 rounded`}>
                    <label htmlFor={'clone_plugin_template'} css={tw`text-base block mb-2`}>
                        Plugin Removal Template
                    </label>
                    <Input
                        id={'clone_plugin_template'}
                        value={pluginTemplate}
                        onChange={(e) => setPluginTemplate(e.target.value)}
                        placeholder={'luckperms*,litebans*,coreprotect*'}
                    />
                    <span css={tw`text-xs text-neutral-400 mt-2 block`}>
                        Comma-separated plugin name patterns. Matching is case-insensitive and files only —
                        e.g. <code css={tw`text-neutral-300`}>goxy*</code> matches <code css={tw`text-neutral-300`}>Goxy.jar</code>.
                    </span>
                </p>
            </Dialog.Confirm>
            <Dialog.Confirm
                title={`Delete "${backup.name}"`}
                confirm={'Continue'}
                open={modal === 'delete'}
                onClose={() => setModal('')}
                onConfirmed={doDeletion}
            >
                This is a permanent operation. The backup cannot be recovered once deleted.
            </Dialog.Confirm>
            <SpinnerOverlay visible={loading} fixed />
            {backup.isSuccessful ? (
                <DropdownMenu
                    renderToggle={(onClick) => (
                        <button
                            onClick={onClick}
                            css={tw`text-gray-200 transition-colors duration-150 hover:text-gray-100 p-2`}
                        >
                            <FontAwesomeIcon icon={faEllipsisH} />
                        </button>
                    )}
                >
                    <div css={tw`text-sm`}>
                        <Can action={'backup.download'}>
                            <DropdownButtonRow onClick={doDownload}>
                                <FontAwesomeIcon fixedWidth icon={faCloudDownloadAlt} css={tw`text-xs`} />
                                <span css={tw`ml-2`}>Download</span>
                            </DropdownButtonRow>
                        </Can>
                        <Can action={'backup.restore'}>
                            <DropdownButtonRow onClick={() => setModal('restore')}>
                                <FontAwesomeIcon fixedWidth icon={faBoxOpen} css={tw`text-xs`} />
                                <span css={tw`ml-2`}>Restore</span>
                            </DropdownButtonRow>
                            <DropdownButtonRow onClick={() => setModal('create-server')}>
                                <FontAwesomeIcon fixedWidth icon={faPlus} css={tw`text-xs`} />
                                <span css={tw`ml-2`}>Create Server</span>
                            </DropdownButtonRow>
                        </Can>
                        <Can action={'backup.delete'}>
                            <>
                                <DropdownButtonRow onClick={onLockToggle}>
                                    <FontAwesomeIcon
                                        fixedWidth
                                        icon={backup.isLocked ? faUnlock : faLock}
                                        css={tw`text-xs mr-2`}
                                    />
                                    {backup.isLocked ? 'Unlock' : 'Lock'}
                                </DropdownButtonRow>
                                {!backup.isLocked && canDeleteBackup && (
                                    <DropdownButtonRow danger onClick={() => setModal('delete')}>
                                        <FontAwesomeIcon fixedWidth icon={faTrashAlt} css={tw`text-xs`} />
                                        <span css={tw`ml-2`}>Delete</span>
                                    </DropdownButtonRow>
                                )}
                            </>
                        </Can>
                    </div>
                </DropdownMenu>
            ) : (
                <button
                    onClick={() => setModal('delete')}
                    css={tw`text-gray-200 transition-colors duration-150 hover:text-gray-100 p-2`}
                >
                    <FontAwesomeIcon icon={faTrashAlt} />
                </button>
            )}
        </>
    );
};
