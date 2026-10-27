import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                d="M8 12.5 3 20l5 7.5M32 12.5l5 7.5-5 7.5"
                stroke="currentColor"
                strokeWidth="3"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M15 9h10v22H15z"
                stroke="currentColor"
                strokeWidth="3"
                strokeLinejoin="round"
            />
            <path
                d="M15 16h10M15 24h10"
                stroke="currentColor"
                strokeWidth="3"
            />
        </svg>
    );
}
