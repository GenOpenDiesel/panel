import React from 'react';
import { NavLink, useRouteMatch } from 'react-router-dom';
import tw from 'twin.macro';
import { useStoreState } from '@/state/hooks';

export default () => {
    const match = useRouteMatch<{ id: string }>();
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const baseUrl = `/server/${match.params.id}/files`;

    return (
        <div css={tw`flex flex-wrap gap-2 mb-4 border-b border-neutral-700 pb-3`}>
            <NavLink to={baseUrl} exact css={tw`px-3 py-1.5 rounded text-sm text-neutral-300 hover:text-neutral-100`} activeClassName={'!text-neutral-100 bg-neutral-600'}>
                Przeglądarka
            </NavLink>
            {rootAdmin && (
                <NavLink
                    to={`${baseUrl}/search`}
                    css={tw`px-3 py-1.5 rounded text-sm text-neutral-300 hover:text-neutral-100`}
                    activeClassName={'!text-neutral-100 bg-neutral-600'}
                >
                    Szukanie słowne
                </NavLink>
            )}
        </div>
    );
};
