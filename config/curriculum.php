<?php

return [
    'name' => 'Nathan Rodrigues Oliveira',
    'professional_name' => 'Nathan Luceat',
    'title' => 'Desenvolvedor Backend | PHP · Laravel · MySQL',
    'subtitle' => 'Estudante de Engenharia de Software no Inatel · Suporte Técnico',
    'location' => 'Santa Rita do Sapucaí, MG, Brasil',
    'email' => 'nathanluceat@gmail.com',
    'phone' => '+55 (35) 98893-3911',
    'phone_uri' => 'tel:+5535988933911',
    'whatsapp' => 'https://wa.me/5535988933911',
    'github' => 'https://github.com/NathanLuceat',
    'portfolio' => 'https://yourceat.laravel.cloud',
    'summary' => [
        'Estudante de Engenharia de Software no Inatel, com experiência profissional em suporte técnico e desenvolvimento de aplicações web. Possuo conhecimentos práticos em PHP, Laravel, MySQL, APIs REST e Docker, aplicados em projetos com autenticação, bancos de dados relacionais, regras de negócio e integração entre sistemas.',
        'Atualmente atuo com helpdesk, investigação de problemas técnicos e utilização pontual de consultas SQL. Também desenvolvo projetos próprios e soluções web freelance, buscando oportunidades de estágio ou desenvolvimento backend júnior.',
    ],
    'skills' => [
        'Backend' => ['PHP', 'Laravel', 'APIs REST', 'Autenticação e autorização', 'Arquitetura MVC', 'Eloquent ORM', 'Regras de negócio', 'Integração entre aplicações'],
        'Bancos de dados' => ['MySQL', 'SQL', 'Modelagem relacional', 'Migrations', 'Operações CRUD', 'Consultas e manipulação de dados'],
        'Frontend' => ['HTML5', 'CSS3', 'JavaScript', 'TypeScript'],
        'Ferramentas e infraestrutura' => ['Git e GitHub', 'Docker e Docker Compose', 'Laravel Sail', 'Linux/WSL', 'GitHub Actions'],
        'Práticas de desenvolvimento' => ['Testes automatizados', 'Debugging e troubleshooting', 'Integração de APIs', 'Tratamento de erros', 'Versionamento', 'Desenvolvimento de aplicações web'],
        'Conhecimentos complementares' => ['Python', 'C++'],
    ],
    'experience' => [
        [
            'role' => 'Suporte Técnico',
            'organization' => 'Sisponto Sistemas Inteligentes',
            'period' => 'Atual',
            'details' => [
                'Atendimento de helpdesk e suporte aos usuários dos sistemas da empresa.',
                'Identificação, investigação e acompanhamento de problemas técnicos.',
                'Orientação sobre funcionalidades e rotinas dos sistemas utilizados.',
                'Utilização pontual de consultas SQL em bancos MySQL para auxiliar na análise de ocorrências.',
                'Aplicação de conhecimento técnico e funcional para solucionar demandas dos usuários.',
            ],
        ],
        [
            'role' => 'Desenvolvedor Web Freelance',
            'organization' => 'Desenvolvimento web para clientes',
            'period' => null,
            'details' => [
                'Desenvolvimento de landing pages e sites institucionais para clientes.',
                'Criação de interfaces responsivas e soluções voltadas à apresentação de negócios.',
                'Implementação de funcionalidades conforme os requisitos de cada projeto.',
                'Organização de projetos web e atenção à usabilidade, manutenção e experiência do usuário.',
            ],
        ],
    ],
    'projects' => [
        [
            'name' => 'Webblioteca',
            'description' => 'Sistema de gerenciamento de biblioteca, com empréstimos de livros e reservas de salas, autenticação de usuários, permissões e regras de disponibilidade. Conta com API REST de atividades, modelagem relacional e testes automatizados.',
            'technologies' => 'PHP · Laravel · MySQL · Docker / Sail · Laravel Sanctum · PHPUnit',
            'url' => 'https://github.com/NathanLuceat/webblioteca',
            'label' => 'github.com/NathanLuceat/webblioteca',
            'featured' => true,
        ],
        [
            'name' => 'Yourceat',
            'description' => 'Portfólio profissional em Laravel com integração às APIs do GitHub e da Webblioteca, autenticação entre serviços e sincronização incremental. Implementa cache com fallback, tratamento de falhas e testes automatizados.',
            'technologies' => 'PHP · Laravel · MySQL · Blade · Docker · PHPUnit · GitHub Actions',
            'url' => 'https://github.com/NathanLuceat/yourceat',
            'label' => 'github.com/NathanLuceat/yourceat',
            'featured' => true,
        ],
        [
            'name' => 'Hidrauboa',
            'description' => 'Site institucional e catálogo para uma empresa de hidráulica, com navegação responsiva, apresentação de produtos e serviços e direcionamento do atendimento para WhatsApp.',
            'technologies' => 'TypeScript · Vite · Tailwind CSS',
            'url' => 'https://github.com/NathanLuceat/hidrauboa-site',
            'label' => 'github.com/NathanLuceat/hidrauboa-site',
            'featured' => false,
        ],
    ],
    'education' => [
        [
            'course' => 'Engenharia de Software',
            'institution' => 'Instituto Nacional de Telecomunicações — Inatel',
            'status' => 'Graduação em andamento',
            'online_only' => false,
        ],
        [
            'course' => 'Ensino médio',
            'institution' => 'Colégio Padre Júlio Maria, Boa Esperança, MG',
            'status' => 'Fevereiro de 2023 a dezembro de 2025 | Concluído',
            'online_only' => true,
        ],
    ],
    // Somente metadados públicos; documentos originais contêm dados pessoais.
    'certifications' => [
        [
            'title' => 'Desenvolvimento Backend com PHP: POO e Integração com MySQL',
            'issuer' => 'Instituto Federal do Rio Grande do Sul (IFRS)',
            'hours' => 30,
            'completed_at' => '2026-11-12',
            'year' => '2026',
            'validation_url' => null,
        ],
        [
            'title' => 'PHP para Iniciantes: Fundamentos Práticos para o Desenvolvimento Web',
            'issuer' => 'Instituto Federal do Rio Grande do Sul (IFRS)',
            'hours' => 30,
            'completed_at' => '2026-11-05',
            'year' => '2026',
            'validation_url' => null,
        ],
        [
            'title' => 'Banco de Dados 1: fundamentos',
            'issuer' => 'Instituto Federal do Rio Grande do Sul (IFRS)',
            'hours' => 20,
            'completed_at' => '2026-11-05',
            'year' => '2026',
            'validation_url' => null,
        ],
    ],
    'awards' => [
        [
            'title' => 'Start_Engenharia — Programação 1.0',
            'context' => 'Competição de programação em Python',
            'details' => [
                'Fase escolar: 1º lugar em 2024 e 2025; 3º lugar em 2023.',
                'Fase final: 2º lugar em 2024, 3º lugar em 2025 e 19º lugar em 2023.',
            ],
        ],
        [
            'title' => 'Torneio Brasil de Robótica',
            'context' => null,
            'details' => ['1º lugar na fase inicial em 2022.'],
        ],
    ],
    'languages' => [
        'Português' => 'nativo',
        'Inglês' => 'fluente, nível C2 (autoavaliação; sem certificação internacional)',
    ],
];
