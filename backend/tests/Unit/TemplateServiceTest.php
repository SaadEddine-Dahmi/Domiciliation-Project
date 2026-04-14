<?php
// tests/Unit/TemplateServiceTest.php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TemplateService;

class TemplateServiceTest extends TestCase
{
    private TemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TemplateService();
    }

    /** @test */
    public function replaces_single_variable(): void
    {
        $result = $this->service->render(
            'Bonjour {{raison_sociale}}',
            ['raison_sociale' => 'BRONX IMMOBILIER']
        );

        $this->assertStringContainsString('<strong>BRONX IMMOBILIER</strong>', $result);
        $this->assertStringNotContainsString('{{raison_sociale}}', $result);
    }

    /** @test */
    public function replaces_multiple_variables(): void
    {
        $result = $this->service->render(
            'Du {{date_debut}} au {{date_fin}}',
            [
                'date_debut' => '05/03/2026',
                'date_fin'   => '05/03/2027',
            ]
        );

        $this->assertStringContainsString('<strong>05/03/2026</strong>', $result);
        $this->assertStringContainsString('<strong>05/03/2027</strong>', $result);
    }

    /** @test */
    public function unreplaced_variables_shown_in_gold_italic(): void
    {
        $result = $this->service->render(
            'Ville: {{ville_signature}}',
            [] // no data provided
        );

        // Unreplaced vars should be shown visibly, not silently dropped
        $this->assertStringContainsString('ville_signature', $result);
        $this->assertStringNotContainsString('{{ville_signature}}', $result);
    }

    /** @test */
    public function escapes_html_in_variable_values(): void
    {
        $result = $this->service->render(
            'Société: {{raison_sociale}}',
            ['raison_sociale' => '<script>alert("xss")</script>']
        );

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    /** @test */
    public function handles_empty_body_gracefully(): void
    {
        $result = $this->service->render('', ['key' => 'value']);
        $this->assertEquals('', $result);
    }

    /** @test */
    public function handles_empty_data_array(): void
    {
        $result = $this->service->render('Static text only.', []);
        $this->assertStringContainsString('Static text only.', $result);
    }
}
