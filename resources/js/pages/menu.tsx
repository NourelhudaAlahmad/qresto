import { Head } from '@inertiajs/react';

type MenuProps = {
    restaurant: {
        name: string;
    };
    table: {
        number: string;
    };
    session: {
        token: string;
        active: boolean;
    };
};

export default function Menu({ restaurant, table, session }: MenuProps) {
    return (
        <>
            <Head title={`${restaurant.name} - Menu`} />

            <main>
                <h1>{restaurant.name}</h1>

                <p>Table: {table.number}</p>

                <p>
                    Session: {session.active ? 'Active' : 'Inactive'}
                </p>

                <p>Welcome! Your table is ready.</p>
            </main>
        </>
    );
}

