import { Head } from '@inertiajs/react';

const apiEndpoints = [
    { method: 'POST', path: '/api/open-banking/consents/v3/consents' },
    { method: 'GET', path: '/api/open-banking/accounts/v2/accounts' },
    { method: 'POST', path: '/api/open-banking/payments/v5/pix/payments' },
    { method: 'GET', path: '/api/open-banking/resources/v3/resources' },
];

const stack = [
    'Laravel 13',
    'Open Finance Brasil',
    'Event Sourcing',
    'Kafka',
    'PostgreSQL',
    'CQRS',
];

export default function Welcome() {
    const appName = import.meta.env.VITE_APP_NAME || 'Wallet';

    return (
        <>
            <Head title="Carteira Digital" />
            <div className="min-h-screen bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="mx-auto flex min-h-screen max-w-4xl flex-col px-6 py-16 lg:px-8">
                    <header className="mb-16">
                        <p className="mb-3 text-sm font-medium tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">
                            Open Finance · Event-Driven
                        </p>
                        <h1 className="text-4xl font-semibold tracking-tight lg:text-5xl">
                            {appName}
                        </h1>
                        <p className="mt-4 max-w-2xl text-lg leading-relaxed text-[#706f6c] dark:text-[#A1A09A]">
                            Carteira digital API-first, alinhada aos padrões do Open
                            Finance Brasil, com arquitetura orientada a eventos e
                            integração plugável com bancos e fintechs participantes.
                        </p>
                    </header>

                    <main className="grid flex-1 gap-8 lg:grid-cols-2">
                        <section className="rounded-xl border border-[#e3e3e0] bg-white p-6 dark:border-[#3E3E3A] dark:bg-[#161615]">
                            <h2 className="mb-4 text-lg font-medium">
                                Sobre o projeto
                            </h2>
                            <ul className="space-y-3 text-sm leading-6 text-[#706f6c] dark:text-[#A1A09A]">
                                <li>
                                    <strong className="text-[#1b1b18] dark:text-[#EDEDEC]">
                                        Detentora de conta
                                    </strong>{' '}
                                    — expõe APIs conformes (consentimentos, contas,
                                    PIX, recursos).
                                </li>
                                <li>
                                    <strong className="text-[#1b1b18] dark:text-[#EDEDEC]">
                                        Event Sourcing
                                    </strong>{' '}
                                    — Kafka como log primário de eventos; projeções
                                    em PostgreSQL para leitura (CQRS).
                                </li>
                                <li>
                                    <strong className="text-[#1b1b18] dark:text-[#EDEDEC]">
                                        Adapters plugáveis
                                    </strong>{' '}
                                    — integração simplificada com participantes que
                                    já exportam APIs Open Finance.
                                </li>
                                <li>
                                    <strong className="text-[#1b1b18] dark:text-[#EDEDEC]">
                                        Antifraude
                                    </strong>{' '}
                                    — regras de velocity e limites integradas ao fluxo
                                    de pagamentos.
                                </li>
                            </ul>
                        </section>

                        <section className="rounded-xl border border-[#e3e3e0] bg-white p-6 dark:border-[#3E3E3A] dark:bg-[#161615]">
                            <h2 className="mb-4 text-lg font-medium">Stack</h2>
                            <div className="flex flex-wrap gap-2">
                                {stack.map((item) => (
                                    <span
                                        key={item}
                                        className="rounded-full border border-[#e3e3e0] px-3 py-1 text-xs font-medium dark:border-[#3E3E3A]"
                                    >
                                        {item}
                                    </span>
                                ))}
                            </div>
                            <p className="mt-6 text-sm leading-6 text-[#706f6c] dark:text-[#A1A09A]">
                                Esta interface é apenas informativa. Toda a operação
                                da carteira é feita via API REST em{' '}
                                <code className="rounded bg-[#f5f5f4] px-1.5 py-0.5 text-xs dark:bg-[#262625]">
                                    /api/open-banking/
                                </code>
                                .
                            </p>
                        </section>

                        <section className="rounded-xl border border-[#e3e3e0] bg-white p-6 lg:col-span-2 dark:border-[#3E3E3A] dark:bg-[#161615]">
                            <h2 className="mb-4 text-lg font-medium">
                                Endpoints principais
                            </h2>
                            <ul className="divide-y divide-[#e3e3e0] dark:divide-[#3E3E3A]">
                                {apiEndpoints.map((endpoint) => (
                                    <li
                                        key={endpoint.path}
                                        className="flex items-center gap-4 py-3 font-mono text-sm"
                                    >
                                        <span className="w-14 shrink-0 rounded bg-[#1b1b18] px-2 py-0.5 text-center text-xs font-semibold text-white dark:bg-[#EDEDEC] dark:text-[#1b1b18]">
                                            {endpoint.method}
                                        </span>
                                        <span className="text-[#706f6c] dark:text-[#A1A09A]">
                                            {endpoint.path}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    </main>

                    <footer className="mt-16 border-t border-[#e3e3e0] pt-8 text-sm text-[#706f6c] dark:border-[#3E3E3A] dark:text-[#A1A09A]">
                        <p className="mb-4">
                            <a
                                href="/docs/api"
                                className="inline-flex items-center rounded-sm border border-[#19140035] px-4 py-2 font-medium text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC]"
                            >
                                Documentação Swagger / OpenAPI
                            </a>
                        </p>
                        <p>
                            Documentação técnica em{' '}
                            <a
                                href="https://github.com/OpenBanking-Brasil/specs"
                                target="_blank"
                                rel="noreferrer"
                                className="font-medium text-[#f53003] underline underline-offset-4 dark:text-[#FF4433]"
                            >
                                Open Finance Brasil
                            </a>
                            . Consulte o README do repositório para deploy e testes.
                        </p>
                    </footer>
                </div>
            </div>
        </>
    );
}
