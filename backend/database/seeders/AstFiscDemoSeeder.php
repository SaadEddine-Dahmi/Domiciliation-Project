<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\AppNotification;
use App\Models\Alerte;
use App\Models\Contrat;
use App\Models\DomiciliataireProfile;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Entreprise;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Representant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AstFiscDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@domiciliation.ma'],
            [
                'nom' => 'Super',
                'prenom' => 'Admin',
                'password' => Hash::make('password123'),
                'telephone' => '+212600000000',
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        $domiciliataireUser = User::updateOrCreate(
            ['email' => 'contact@ast-fisc.ma'],
            [
                'nom' => 'KHARBOUCHE',
                'prenom' => 'ANOUAR RACHID',
                'password' => Hash::make('password123'),
                'telephone' => '+212528000000',
                'role' => 'domiciliataire',
                'status' => 'active',
            ]
        );

        DomiciliataireProfile::updateOrCreate(
            ['user_id' => $domiciliataireUser->id],
            [
                'nom_societe' => 'AST-FISC SARL AU',
                'contract_title' => 'CONTRAT DE DOMICILIATION',
                'rc' => '56989',
                'if_fiscal' => '60102285',
                'tp' => '55004406',
                'adresses' => [
                    [
                        'label' => 'Siege social',
                        'value' => 'Siege N° 78 KASBAR SOUSS KM5 BENSERGAO AGADIR - Maroc',
                    ],
                    [
                        'label' => 'Succursale 1',
                        'value' => 'APPT N°4 IMM 617 AV MOHAMED EL FASSI RUE 951 HAY SALAM AGADIR',
                    ],
                    [
                        'label' => 'Succursale 2',
                        'value' => 'IMM: 129 BUREAU 22 ETAGE 2 HAUT FOUNTY AGADIR',
                    ],
                ],
            ]
        );

        Representant::updateOrCreate(
            [
                'representable_type' => User::class,
                'representable_id' => $domiciliataireUser->id,
            ],
            [
                'nom' => 'KHARBOUCHE',
                'prenom' => 'ANOUAR RACHID',
                'cin' => 'J284560',
                'nationalite' => 'Marocaine',
                'telephone' => '+212528000000',
                'email' => 'contact@ast-fisc.ma',
            ]
        );

        $clientUser = User::updateOrCreate(
            ['email' => 'laetitia@saharaexploration.com'],
            [
                'nom' => 'WONG',
                'prenom' => 'LAETITIA',
                'password' => Hash::make('password123'),
                'telephone' => '+33626011149',
                'role' => 'client',
                'status' => 'active',
            ]
        );

        $entreprise = Entreprise::updateOrCreate(
            [
                'domiciliataire_id' => $domiciliataireUser->id,
                'client_user_id' => $clientUser->id,
                'raison_sociale' => 'West Odyssee',
            ],
            [
                'forme_juridique' => 'SARL',
                'adresse' => 'C/O AST-FISC N°78 KASBAT SOUSS BENSERGAO AGADIR - Maroc',
                'ville' => 'Agadir',
                'pays' => 'Maroc',
                'statut' => 'actif',
            ]
        );

        Representant::updateOrCreate(
            [
                'representable_type' => Entreprise::class,
                'representable_id' => $entreprise->id,
            ],
            [
                'nom' => 'WONG',
                'prenom' => 'LAETITIA',
                'cin' => '19AC67035',
                'date_naissance' => '1986-01-17',
                'adresse' => '5 Avenue Charcot 92600 Asnieres-sur-Seine France',
                'telephone' => '+33626011149',
                'email' => 'laetitia@saharaexploration.com',
            ]
        );

        $articles = [
            [
                'title' => 'DUREE',
                'body' => "Le present contrat est prevu pour une duree de {{duree_mois}} Mois qui commencera le {{date_debut}} et se terminera le {{date_fin}}.\nLes deux parties pourront resilier le present contrat par lettre recommandee avec accuse de reception, en respectant le preavis de (1) mois.\nAvant expiration de la duree du preavis, le client devra justifier aupres du {{domiciliataire_nom}} soit de son transfert de siege social soit de la dissolution de son entreprise par la remise d'un extrait de registre du commerce modificatif, A defaut, les honoraires resteront dus jusqu'a justification.",
            ],
            [
                'title' => 'DEFINITION DES PRESTATIONS',
                'body' => "- Attribution de l'adresse commerciale et postale\n- Reception du courrier destine au client.\nLe centre de domiciliation s'engage a conserver le courrier a destination du client pour une duree n'excedant pas 3 mois a l'expiration de ce delai il decline toute responsabilite par rapport a la perte ou l'engagement de ce courrier.",
            ],
            [
                'title' => 'FONCTIONNEMENT DU SERVICE',
                'body' => "Horaires: les services decrits ci-dessus sont fournis en fonction des horaires qui sont fixes comme suit: Matin de 9h00 a 12h00 - Apres-midi de 15h00 a 17h00 et le samedi matin de 9h00 a 12h00 Sauf dimanche et jour ferie.",
            ],
            [
                'title' => 'RENOUVELLEMENT',
                'body' => "La domiciliation est renouvelee, 1 (un) mois avant la date d'echeance.",
            ],
            [
                'title' => 'RENSEIGNEMENTS',
                'body' => "Le signataire du present contrat declare, et certifie sur l'honneur, certifier l'exactitude des renseignements fournis a la societe {{domiciliataire_nom}}.",
            ],
            [
                'title' => 'RESILIATION',
                'body' => "Le domiciliataire se reserve le droit, dans les 8 jours suivant une mise en demeure notifiee au domicilie de resilier le present contrat sans autre formalite dans les cas suivants:\n- Inexecution pour le domicilier de l'une de ses obligations.\n- Fausse information donnee par le domicilier au domiciliataire sur sa situation.\n- Entrave a la bonne marche du domiciliataire et atteinte a sa reputation ou son enseigne.",
            ],
            [
                'title' => 'OBLIGATIONS',
                'body' => "Pendant toute la duree du contrat, le citoyen doit respecter les obligations suivantes :\n- Tenir un dossier sur chaque personne domiciliee qui contient les pieces justificatives relatives aux personnes physiques, leurs Adresses personnelles, numeros de telephone et numeros de carte d'identite, ainsi que leurs adresses-mail, et s'agissant des personnes morales, les documents prouvant les adresses, numeros de telephone numeros et cartes d'identite de Leurs responsables, ainsi que leurs adresses-mail...\n- Veiller a ce que le citoyen soit inscrit au registre du commerce dans un delai de trois mois compter de la date de conclusion Du Contrat...\n- Supportant solidairement le paiement des impots et taxes lies a l'activite exercee par le domicile...",
            ],
            [
                'title' => 'RESPONSABILITE',
                'body' => "Mr. {{domiciliataire_representant}} a decharge {{domiciliataire_nom}} de toute responsabilite concernant les poursuites pour cheques sans provision de la societe {{raison_sociale}}, ainsi que de toutes poursuites pour recouvrement des creances. La societe {{raison_sociale}} doit justifier de son identite, de son domicile. S'il est en personne morale, il doit remettre a {{domiciliataire_nom}} un exemplaire certifie conforme de son statut et de son immatriculation au registre de commerce dans le delai d'(1) un mois.\n{{domiciliataire_nom}} est quitte de tout engagement etabli par la societe {{raison_sociale}} envers L'ETAT, Fournisseurs, Les Etablissement de credits. Etc. Le gerant ou l'administrateur de la societe {{raison_sociale}} s'interdit par sa signature sur le present contrat de donner en nantissements le fonds de commerce de {{domiciliataire_nom}} ou tout autre actif appartenant a celle-ci.",
            ],
            [
                'title' => 'CLAUSE RESOLUTOIRE',
                'body' => "En cas de litige pour non payement des redevances convenues par le present contrat, tous les frais, honoraires d'Avocat, representations par mandataires, expertises, engages par le domiciliataire, seront entierement et totalement a la charge de {{gerant_nom}} au meme rang que les redevances mensuelles dues, ainsi que tous les frais de justice qui lui incomberaient de droit.",
            ],
            [
                'title' => 'FRAIS',
                'body' => "Les frais et droits de la presente sont a la charge du client. En cas de contestation du present seul le tribunal de {{ville_signature}} sera competent.",
            ],
            [
                'title' => 'MANDAT',
                'body' => "Le domicilie declare donner mandat a la societe domiciliataire pour recevoir en son nom toute notifications emanant des administrations ou bien des tiers.",
            ],
            [
                'title' => 'REDEVANCE',
                'body' => "Le present contrat est consenti moyennant une redevance mensuelle de {{prix_mensuel}} dh, soit {{prix_total}} dh Annuelle payable d'avance.",
            ],
            [
                'title' => 'CONTACT',
                'body' => "Je certifie, {{gerant_nom}} l'exactitude des informations ci-dessous:\nN° Tel: {{gerant_telephone}}\nEmail: {{gerant_email}}\nAdresse personnelle: {{gerant_adresse}}",
            ],
        ];

        foreach ($articles as $article) {
            Article::updateOrCreate(
                [
                    'domiciliataire_id' => $domiciliataireUser->id,
                    'title' => $article['title'],
                ],
                [
                    'body' => $article['body'],
                    'is_active' => true,
                ]
            );
        }

        $documentTypes = $this->seedDocumentTypes();

        $this->seedTenantData($domiciliataireUser, [
            [
                'client' => [
                    'nom' => 'WONG',
                    'prenom' => 'LAETITIA',
                    'email' => 'laetitia@saharaexploration.com',
                    'telephone' => '+33626011149',
                ],
                'entreprise' => [
                    'raison_sociale' => 'West Odyssee',
                    'forme_juridique' => 'SARL',
                    'adresse' => 'C/O AST-FISC N°78 KASBAT SOUSS BENSERGAO AGADIR - Maroc',
                    'ville' => 'Agadir',
                    'pays' => 'Maroc',
                    'statut' => 'actif',
                ],
                'representant' => [
                    'nom' => 'WONG',
                    'prenom' => 'LAETITIA',
                    'cin' => '19AC67035',
                    'date_naissance' => '1986-01-17',
                    'adresse' => '5 Avenue Charcot 92600 Asnieres-sur-Seine France',
                    'telephone' => '+33626011149',
                    'email' => 'laetitia@saharaexploration.com',
                ],
                'contract' => [
                    'instruction_no' => 'AST-2026-001',
                    'date_debut' => '2026-01-01',
                    'date_fin' => '2026-12-31',
                    'prix_mensuel' => 450,
                    'statut' => 'active',
                    'scanned_pdf_path' => 'demo/contracts/west-odyssee-legalise.pdf',
                ],
                'invoice' => ['montant_total' => 5400, 'statut' => 'paid', 'paid' => true],
            ],
            [
                'client' => [
                    'nom' => 'AIT',
                    'prenom' => 'YASSINE',
                    'email' => 'yassine@atlas-digital.ma',
                    'telephone' => '+212661445566',
                ],
                'entreprise' => [
                    'raison_sociale' => 'Atlas Digital Services',
                    'forme_juridique' => 'SARL AU',
                    'adresse' => 'Quartier Industriel Ait Melloul - Maroc',
                    'ville' => 'Agadir',
                    'pays' => 'Maroc',
                    'statut' => 'actif',
                ],
                'representant' => [
                    'nom' => 'AIT',
                    'prenom' => 'YASSINE',
                    'cin' => 'J448812',
                    'date_naissance' => '1991-04-12',
                    'adresse' => 'Bloc C Residence Salam Agadir',
                    'telephone' => '+212661445566',
                    'email' => 'yassine@atlas-digital.ma',
                ],
                'contract' => [
                    'instruction_no' => 'AST-2026-002',
                    'date_debut' => '2026-06-01',
                    'date_fin' => '2027-05-31',
                    'prix_mensuel' => 350,
                    'statut' => 'active',
                ],
                'invoice' => ['montant_total' => 4200, 'statut' => 'pending', 'paid' => false],
            ],
            [
                'client' => [
                    'nom' => 'BENALI',
                    'prenom' => 'SARA',
                    'email' => 'sara@nomad-craft.ma',
                    'telephone' => '+212670112233',
                ],
                'entreprise' => [
                    'raison_sociale' => 'Nomad Craft Export',
                    'forme_juridique' => 'SARL',
                    'adresse' => 'Hay Mohammadi Agadir - Maroc',
                    'ville' => 'Agadir',
                    'pays' => 'Maroc',
                    'statut' => 'actif',
                ],
                'representant' => [
                    'nom' => 'BENALI',
                    'prenom' => 'SARA',
                    'cin' => 'J778899',
                    'date_naissance' => '1988-09-03',
                    'adresse' => 'Rue Marrakech Agadir',
                    'telephone' => '+212670112233',
                    'email' => 'sara@nomad-craft.ma',
                ],
                'contract' => [
                    'instruction_no' => 'AST-2025-014',
                    'date_debut' => '2025-09-01',
                    'date_fin' => '2026-08-31',
                    'prix_mensuel' => 300,
                    'statut' => 'active',
                ],
                'invoice' => ['montant_total' => 3600, 'statut' => 'pending', 'paid' => false],
            ],
        ], $documentTypes);

        $extraTenants = [
            [
                'user' => [
                    'nom' => 'EL MANSOURI',
                    'prenom' => 'NADIA',
                    'email' => 'contact@rabat-business-center.ma',
                    'telephone' => '+212537220011',
                ],
                'profile' => [
                    'nom_societe' => 'Rabat Business Center SARL',
                    'rc' => '88214',
                    'if_fiscal' => '40211890',
                    'tp' => '99011422',
                    'adresses' => [
                        ['label' => 'Siege social', 'value' => 'Avenue Annakhil Hay Riad Rabat - Maroc'],
                    ],
                ],
                'clients' => [
                    [
                        'client' => ['nom' => 'FERRARI', 'prenom' => 'MARCO', 'email' => 'marco@medina-foods.ma', 'telephone' => '+393331112233'],
                        'entreprise' => ['raison_sociale' => 'Medina Foods Import', 'forme_juridique' => 'SARL', 'adresse' => 'Hay Riad Rabat - Maroc', 'ville' => 'Rabat', 'pays' => 'Maroc', 'statut' => 'actif'],
                        'representant' => ['nom' => 'FERRARI', 'prenom' => 'MARCO', 'cin' => 'YA554433', 'date_naissance' => '1982-02-20', 'adresse' => 'Rabat Agdal', 'telephone' => '+393331112233', 'email' => 'marco@medina-foods.ma'],
                        'contract' => ['instruction_no' => 'RBC-2026-001', 'date_debut' => '2026-03-01', 'date_fin' => '2027-02-28', 'prix_mensuel' => 500, 'statut' => 'active'],
                        'invoice' => ['montant_total' => 6000, 'statut' => 'paid', 'paid' => true],
                    ],
                    [
                        'client' => ['nom' => 'EL HARTI', 'prenom' => 'IMANE', 'email' => 'imane@green-supply.ma', 'telephone' => '+212698554411'],
                        'entreprise' => ['raison_sociale' => 'Green Supply Africa', 'forme_juridique' => 'SARL AU', 'adresse' => 'Technopolis Sale - Maroc', 'ville' => 'Sale', 'pays' => 'Maroc', 'statut' => 'actif'],
                        'representant' => ['nom' => 'EL HARTI', 'prenom' => 'IMANE', 'cin' => 'AA220011', 'date_naissance' => '1990-11-18', 'adresse' => 'Temara Centre', 'telephone' => '+212698554411', 'email' => 'imane@green-supply.ma'],
                        'contract' => ['instruction_no' => 'RBC-2026-002', 'date_debut' => '2026-07-01', 'date_fin' => '2027-06-30', 'prix_mensuel' => 420, 'statut' => 'draft'],
                        'invoice' => ['montant_total' => 5040, 'statut' => 'pending', 'paid' => false],
                    ],
                ],
            ],
            [
                'user' => [
                    'nom' => 'BOUZID',
                    'prenom' => 'KARIM',
                    'email' => 'hello@casablanca-domicile.ma',
                    'telephone' => '+212522440077',
                ],
                'profile' => [
                    'nom_societe' => 'Casablanca Domicile Plus',
                    'rc' => '124578',
                    'if_fiscal' => '77004512',
                    'tp' => '44117890',
                    'adresses' => [
                        ['label' => 'Siege social', 'value' => 'Boulevard Abdelmoumen Casablanca - Maroc'],
                        ['label' => 'Bureau client', 'value' => 'Maarif Extension Casablanca - Maroc'],
                    ],
                ],
                'clients' => [
                    [
                        'client' => ['nom' => 'SMITH', 'prenom' => 'AMELIA', 'email' => 'amelia@atlantic-consulting.ma', 'telephone' => '+447700900123'],
                        'entreprise' => ['raison_sociale' => 'Atlantic Consulting Morocco', 'forme_juridique' => 'SARL', 'adresse' => 'Maarif Casablanca - Maroc', 'ville' => 'Casablanca', 'pays' => 'Maroc', 'statut' => 'actif'],
                        'representant' => ['nom' => 'SMITH', 'prenom' => 'AMELIA', 'cin' => 'GB102030', 'date_naissance' => '1985-06-09', 'adresse' => 'Maarif Casablanca', 'telephone' => '+447700900123', 'email' => 'amelia@atlantic-consulting.ma'],
                        'contract' => ['instruction_no' => 'CDP-2026-001', 'date_debut' => '2026-02-15', 'date_fin' => '2027-02-14', 'prix_mensuel' => 650, 'statut' => 'active', 'scanned_pdf_path' => 'demo/contracts/atlantic-consulting-legalise.pdf'],
                        'invoice' => ['montant_total' => 7800, 'statut' => 'paid', 'paid' => true],
                    ],
                    [
                        'client' => ['nom' => 'CHRAIBI', 'prenom' => 'OMAR', 'email' => 'omar@fintech-lab.ma', 'telephone' => '+212660778899'],
                        'entreprise' => ['raison_sociale' => 'Fintech Lab Maroc', 'forme_juridique' => 'SARL AU', 'adresse' => 'Casa Finance City Casablanca - Maroc', 'ville' => 'Casablanca', 'pays' => 'Maroc', 'statut' => 'actif'],
                        'representant' => ['nom' => 'CHRAIBI', 'prenom' => 'OMAR', 'cin' => 'BE330077', 'date_naissance' => '1993-12-01', 'adresse' => 'Ain Diab Casablanca', 'telephone' => '+212660778899', 'email' => 'omar@fintech-lab.ma'],
                        'contract' => ['instruction_no' => 'CDP-2025-008', 'date_debut' => '2025-08-01', 'date_fin' => '2026-08-25', 'prix_mensuel' => 600, 'statut' => 'expired'],
                        'invoice' => ['montant_total' => 7200, 'statut' => 'cancelled', 'paid' => false],
                    ],
                ],
            ],
        ];

        foreach ($extraTenants as $tenantData) {
            $tenant = User::updateOrCreate(
                ['email' => $tenantData['user']['email']],
                $tenantData['user'] + [
                    'password' => Hash::make('password123'),
                    'role' => 'domiciliataire',
                    'status' => 'active',
                ]
            );

            DomiciliataireProfile::updateOrCreate(
                ['user_id' => $tenant->id],
                $tenantData['profile'] + ['contract_title' => 'CONTRAT DE DOMICILIATION']
            );

            Representant::updateOrCreate(
                ['representable_type' => User::class, 'representable_id' => $tenant->id],
                [
                    'nom' => $tenant->nom,
                    'prenom' => $tenant->prenom,
                    'cin' => strtoupper(substr(md5($tenant->email), 0, 8)),
                    'nationalite' => 'Marocaine',
                    'telephone' => $tenant->telephone,
                    'email' => $tenant->email,
                ]
            );

            $this->seedTenantData($tenant, $tenantData['clients'], $documentTypes);
        }

        AppNotification::updateOrCreate(
            ['user_id' => $admin->id, 'type' => 'domiciliataire_pending', 'subject' => 'Nouvelle demande domiciliataire'],
            [
                'message' => 'Une nouvelle demande de compte domiciliataire est en attente de validation.',
                'data' => ['route' => '/super-admin/dashboard', 'label' => 'Voir les demandes'],
                'is_read' => false,
            ]
        );

        $this->command?->info('Seeded expanded demo data: users, domiciliataires, clients, contracts, invoices, documents, notifications, and messages.');
    }

    private function seedDocumentTypes(): array
    {
        $types = [
            ['name' => 'Extrait RC', 'is_required' => true, 'has_expiration' => true, 'description' => 'Registre de commerce recent.'],
            ['name' => 'CIN Gerant', 'is_required' => true, 'has_expiration' => true, 'description' => 'Piece identite du representant legal.'],
            ['name' => 'Statuts', 'is_required' => true, 'has_expiration' => false, 'description' => 'Statuts de la societe.'],
            ['name' => 'Attestation IF', 'is_required' => false, 'has_expiration' => false, 'description' => 'Identifiant fiscal.'],
        ];

        $records = [];
        foreach ($types as $type) {
            $records[$type['name']] = DocumentType::updateOrCreate(['name' => $type['name']], $type);
        }

        return $records;
    }

    private function seedTenantData(User $domiciliataire, array $clients, array $documentTypes): void
    {
        $articles = Article::where('domiciliataire_id', $domiciliataire->id)->orderBy('id')->limit(5)->get();

        foreach ($clients as $index => $data) {
            $client = User::updateOrCreate(
                ['email' => $data['client']['email']],
                $data['client'] + [
                    'password' => Hash::make('password123'),
                    'role' => 'client',
                    'status' => 'active',
                ]
            );

            $entreprise = Entreprise::updateOrCreate(
                [
                    'domiciliataire_id' => $domiciliataire->id,
                    'client_user_id' => $client->id,
                    'raison_sociale' => $data['entreprise']['raison_sociale'],
                ],
                $data['entreprise']
            );

            Representant::updateOrCreate(
                ['representable_type' => Entreprise::class, 'representable_id' => $entreprise->id],
                $data['representant']
            );

            $start = Carbon::parse($data['contract']['date_debut']);
            $end = Carbon::parse($data['contract']['date_fin']);
            $contract = Contrat::updateOrCreate(
                ['instruction_no' => $data['contract']['instruction_no']],
                [
                    'domiciliataire_id' => $domiciliataire->id,
                    'entreprise_id' => $entreprise->id,
                    'titre_contrat' => 'CONTRAT DE DOMICILIATION',
                    'date_signature' => $start->copy()->subDays(3)->toDateString(),
                    'ville_signature' => $entreprise->ville ?: 'Agadir',
                    'date_debut' => $start->toDateString(),
                    'date_fin' => $end->toDateString(),
                    'duree_mois' => max(1, (int) round($start->floatDiffInMonths($end))),
                    'prix_mensuel' => $data['contract']['prix_mensuel'],
                    'prix_total' => $data['contract']['prix_mensuel'] * 12,
                    'caution' => $data['contract']['prix_mensuel'],
                    'mode_paiement' => 'Virement bancaire',
                    'statut' => $data['contract']['statut'],
                    'scanned_pdf_path' => $data['contract']['scanned_pdf_path'] ?? null,
                    'notification_delay_months' => 1,
                    'next_alert_date' => $end->copy()->subMonth()->toDateString(),
                ]
            );

            if ($articles->isNotEmpty()) {
                $sync = $articles
                    ->mapWithKeys(fn(Article $article, int $position) => [$article->id => ['ordre' => $position + 1]])
                    ->toArray();
                $contract->articles()->sync($sync);
            }

            Alerte::updateOrCreate(
                ['contrat_id' => $contract->id, 'date_alerte' => $end->copy()->subMonth()->toDateString()],
                ['envoye' => $end->isPast()]
            );

            $facture = Facture::updateOrCreate(
                ['contrat_id' => $contract->id, 'entreprise_id' => $entreprise->id],
                [
                    'domiciliataire_id' => $domiciliataire->id,
                    'montant_total' => $data['invoice']['montant_total'],
                    'statut' => $data['invoice']['statut'],
                    'date_facture' => $start->copy()->addDays(2)->toDateString(),
                    'archived_at' => $data['invoice']['statut'] === 'cancelled' ? now() : null,
                ]
            );

            if ($data['invoice']['paid']) {
                Paiement::updateOrCreate(
                    ['facture_id' => $facture->id, 'date_paiement' => $start->copy()->addDays(8)->toDateString()],
                    [
                        'montant' => $data['invoice']['montant_total'],
                        'mode_paiement' => 'Virement bancaire',
                    ]
                );
            }

            foreach ($documentTypes as $name => $type) {
                Document::updateOrCreate(
                    ['entreprise_id' => $entreprise->id, 'document_type_id' => $type->id],
                    [
                        'file_path' => 'demo/documents/' . str($entreprise->raison_sociale)->slug() . '-' . str($name)->slug() . '.pdf',
                        'date_expiration' => $type->has_expiration ? now()->addMonths(6 - $index)->toDateString() : null,
                        'uploaded_by_user' => $domiciliataire->id,
                    ]
                );
            }

            AppNotification::updateOrCreate(
                ['user_id' => $client->id, 'contrat_id' => $contract->id, 'type' => 'contract_legalized'],
                [
                    'subject' => 'Contrat legalise disponible',
                    'message' => "Le contrat de {$entreprise->raison_sociale} est disponible dans votre espace.",
                    'data' => ['route' => "/client/contrats/{$contract->id}", 'label' => 'Voir le contrat'],
                    'is_read' => $index % 2 === 0,
                    'read_at' => $index % 2 === 0 ? now() : null,
                ]
            );

            AppNotification::updateOrCreate(
                ['user_id' => $client->id, 'type' => 'invoice_status', 'subject' => "Facture {$facture->numero_facture}"],
                [
                    'message' => $facture->statut === 'paid'
                        ? "Votre facture {$facture->numero_facture} a ete payee."
                        : "Votre facture {$facture->numero_facture} est en attente de paiement.",
                    'data' => ['route' => "/client/factures/{$facture->id}", 'label' => 'Voir la facture'],
                    'is_read' => false,
                ]
            );

            AppNotification::updateOrCreate(
                ['user_id' => $client->id, 'from_user_id' => $domiciliataire->id, 'subject' => 'Bienvenue sur votre espace client'],
                [
                    'message' => "Bonjour {$client->prenom}, votre espace client pour {$entreprise->raison_sociale} est pret. Vous pouvez consulter vos documents, contrats et factures.",
                    'type' => 'message',
                    'data' => ['route' => '/client/messages', 'label' => 'Voir le message'],
                    'is_read' => false,
                ]
            );

            AppNotification::updateOrCreate(
                ['user_id' => $domiciliataire->id, 'from_user_id' => $client->id, 'subject' => 'Documents transmis'],
                [
                    'message' => "Bonjour, les documents demandes pour {$entreprise->raison_sociale} ont ete transmis. Merci de confirmer la reception.",
                    'type' => 'message',
                    'data' => ['route' => '/admin/messages', 'label' => 'Ouvrir la conversation'],
                    'is_read' => $index % 2 === 1,
                    'read_at' => $index % 2 === 1 ? now() : null,
                ]
            );
        }
    }
}
