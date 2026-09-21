import { SVGAttributes, useId } from 'react';

/**
 * MahbubDesk mark: a ticket stub with punched side notches and a
 * perforated tear line — the ledger's smallest unit of work.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    const maskId = useId();

    return (
        <svg {...props} viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" fill="none">
            <mask id={maskId}>
                <rect x="2.5" y="7" width="27" height="18" rx="3" fill="white" />
                <circle cx="2.5" cy="16" r="3.2" fill="black" />
                <circle cx="29.5" cy="16" r="3.2" fill="black" />
                <rect x="11" y="9.4" width="1.7" height="2.6" rx="0.85" fill="black" />
                <rect x="11" y="13.4" width="1.7" height="2.6" rx="0.85" fill="black" />
                <rect x="11" y="17.4" width="1.7" height="2.6" rx="0.85" fill="black" />
                <rect x="11" y="21.4" width="1.7" height="2.6" rx="0.85" fill="black" />
            </mask>
            <rect x="2.5" y="7" width="27" height="18" rx="3" fill="currentColor" mask={`url(#${maskId})`} />
        </svg>
    );
}
