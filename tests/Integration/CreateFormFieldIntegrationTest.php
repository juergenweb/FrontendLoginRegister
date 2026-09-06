<?php

declare(strict_types=1);

namespace FrontendLoginRegister\Tests\Integration;

require_once __DIR__ . '/bootstrap.php';

use FrontendForms\InputFields;
use FrontendLoginRegister\RegisterPage;
use ProcessWire\Field;

/**
 * Integration coverage for BuildsFormFields::getFormFieldsSelected() and createFormField() against
 * the real registration-form configuration. Both methods are already public, so no pass-through
 * harness is needed - a plain, guest-constructed RegisterPage (already used safely elsewhere in
 * this suite) is enough.
 */
final class CreateFormFieldIntegrationTest extends IntegrationTestCase
{
    public function testGetFormFieldsSelectedResolvesConfiguredFieldIdsToRealFieldObjects(): void
    {
        $page = new RegisterPage();
        $fields = $page->getFormFieldsSelected('input_registration');

        if (!$fields) {
            $this->markTestSkipped(
                'No fields are configured for the registration form (module setting "input_registration").'
            );
        }

        foreach ($fields as $field) {
            $this->assertInstanceOf(Field::class, $field);
        }
    }

    public function testCreateFormFieldBuildsAFrontendFormsFieldWithTheConfiguredFieldName(): void
    {
        $page = new RegisterPage();
        $fields = $page->getFormFieldsSelected('input_registration');

        // find a field this trait actually knows how to build via createFormField() - i.e. not one
        // of the special "system" fields createFormFields() builds manually via its own dedicated
        // methods (createPass(), createEmail(), ...) instead
        $noCreation = ['pass', 'email', 'language', 'tfa', 'username', 'title'];
        $candidate = null;
        foreach ($fields as $field) {
            if (!in_array($field->name, $noCreation, true)) {
                $candidate = $field;
                break;
            }
        }
        if (!$candidate) {
            $this->markTestSkipped(
                'No non-system field is configured for the registration form to build via createFormField().'
            );
        }

        $result = $page->createFormField($candidate);

        $this->assertInstanceOf(InputFields::class, $result);
        $this->assertSame($candidate->name, $result->getAttribute('name'));
    }
}
