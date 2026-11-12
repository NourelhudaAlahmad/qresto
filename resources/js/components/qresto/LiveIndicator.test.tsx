import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { LiveIndicator } from './LiveIndicator';

describe('LiveIndicator', () => {
    it('shows Live when polling is active', () => {
        render(<LiveIndicator isPaused={false} />);

        expect(screen.getByText('Live')).toBeInTheDocument();
    });

    it('shows Paused when polling is paused', () => {
        render(<LiveIndicator isPaused={true} />);

        expect(screen.getByText('Paused')).toBeInTheDocument();
    });
});

describe('LiveIndicator status', () => {
    it('shows Offline in berry when the connection is down', () => {
        render(<LiveIndicator status="offline" />);

        expect(screen.getByRole('status')).toHaveAttribute(
            'data-status',
            'offline',
        );
        expect(screen.getByText('Offline')).toBeInTheDocument();
    });
});

describe('LiveIndicator staleness (#58)', () => {
    it('says how stale the data is when offline', () => {
        render(
            <LiveIndicator
                status="offline"
                lastUpdatedAt={Date.now() - 4 * 60_000}
            />,
        );

        expect(screen.getByTestId('live-staleness')).toHaveTextContent(
            'updated 4 min ago',
        );
    });

    it('says just now for data less than a minute old', () => {
        render(<LiveIndicator status="offline" lastUpdatedAt={Date.now()} />);

        expect(screen.getByTestId('live-staleness')).toHaveTextContent(
            'updated just now',
        );
    });

    it('keeps Live and Paused short: no staleness while trustworthy', () => {
        render(<LiveIndicator status="live" lastUpdatedAt={Date.now()} />);

        expect(screen.queryByTestId('live-staleness')).toBeNull();
    });
});
