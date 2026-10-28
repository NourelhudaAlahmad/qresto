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
