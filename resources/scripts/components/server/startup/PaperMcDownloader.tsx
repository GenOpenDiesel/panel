import React, { useEffect, useMemo, useState } from 'react';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import tw from 'twin.macro';
import Select from '@/components/elements/Select';
import Button from '@/components/elements/Button';
import InputSpinner from '@/components/elements/InputSpinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import Can from '@/components/elements/Can';
import { getPaperVersions } from '@/api/server/startup/getPaperVersions';
import { downloadPaperBuild } from '@/api/server/startup/downloadPaperBuild';
import { ServerEggVariable } from '@/api/server/types';

interface Props {
    variables: ServerEggVariable[];
}

const FLASH_KEY = 'startup:paper';

const PaperMcDownloader = ({ variables }: Props) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const jarVariable = useMemo(
        () => variables.find((variable) => variable.envVariable === 'SERVER_JARFILE'),
        [variables]
    );

    const [loadingVersions, setLoadingVersions] = useState(true);
    const [downloading, setDownloading] = useState(false);
    const [versions, setVersions] = useState<string[]>([]);
    const [selectedVersion, setSelectedVersion] = useState('');
    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();

    useEffect(() => {
        if (!jarVariable) {
            return;
        }

        setLoadingVersions(true);
        clearFlashes(FLASH_KEY);

        getPaperVersions(uuid)
            .then(({ versions: items }) => {
                setVersions(items);
                setSelectedVersion(items[0] || '');
            })
            .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }))
            .then(() => setLoadingVersions(false));
    }, [uuid, jarVariable?.envVariable]);

    if (!jarVariable) {
        return null;
    }

    const jarFileName = jarVariable.serverValue || jarVariable.defaultValue || 'server.jar';

    const onDownload = () => {
        if (!selectedVersion) {
            addFlash({
                key: FLASH_KEY,
                type: 'error',
                message: 'Select a PaperMC version first.',
            });
            return;
        }

        setDownloading(true);
        clearFlashes(FLASH_KEY);

        downloadPaperBuild(uuid, selectedVersion)
            .then(({ version, build, filename, sourceFile }) => {
                addFlash({
                    key: FLASH_KEY,
                    type: 'success',
                    message: `Downloaded Paper ${version} build ${build} (${sourceFile}) as ${filename}.`,
                });
            })
            .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }))
            .then(() => setDownloading(false));
    };

    return (
        <TitledGreyBox title={'PaperMC Build'} css={tw`mt-8`}>
            <FlashMessageRender byKey={FLASH_KEY} css={tw`mb-4`} />
            <p css={tw`text-sm text-neutral-300 mb-4`}>
                Download the latest PaperMC build for a selected Minecraft version. The file will be saved in the
                server root as <code css={tw`text-neutral-200`}>{jarFileName}</code>, overwriting the existing jar.
            </p>
            <div css={tw`grid gap-4 md:grid-cols-[1fr_auto] md:items-end`}>
                <div>
                    <label htmlFor={'paper_version'} css={tw`text-sm text-neutral-300 block mb-2`}>
                        Minecraft Version
                    </label>
                    <InputSpinner visible={loadingVersions}>
                        <Select
                            id={'paper_version'}
                            value={selectedVersion}
                            disabled={loadingVersions || versions.length === 0}
                            onChange={(e) => setSelectedVersion(e.target.value)}
                        >
                            {versions.length === 0 ? (
                                <option value={''}>No versions available</option>
                            ) : (
                                versions.map((version) => (
                                    <option key={version} value={version}>
                                        {version}
                                    </option>
                                ))
                            )}
                        </Select>
                    </InputSpinner>
                </div>
                <Can action={'file.create'}>
                    <InputSpinner visible={downloading}>
                        <Button onClick={onDownload} disabled={loadingVersions || !selectedVersion}>
                            Download Latest Build
                        </Button>
                    </InputSpinner>
                </Can>
            </div>
        </TitledGreyBox>
    );
};

export default PaperMcDownloader;
