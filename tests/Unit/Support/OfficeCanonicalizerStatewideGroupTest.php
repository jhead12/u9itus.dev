<?php

use App\Support\OfficeCanonicalizer;
use App\Support\OfficeGlossary;

it('names each statewide office group instead of a catch-all', function (?string $office, string $group) {
    expect(OfficeCanonicalizer::statewideGroup($office))->toBe($group);
})->with([
    'fixed office' => ['California Attorney General', 'Attorney General'],
    'lieutenant before governor' => ['Lt. Governor', 'Lieutenant Governor'],
    'insurance' => ['Insurance Commissioner', 'Insurance Commissioner'],
    'insurance, other spelling' => ['Commissioner of Insurance', 'Insurance Commissioner'],
    'superintendent' => ['State Superintendent of Public Instruction', 'Superintendent of Public Instruction'],
    'auditor' => ['State Auditor', 'State Auditor'],
    'state senator is legislature' => ['State Senator', 'State Legislature'],
    'assembly' => ['State Assembly Member', 'State Legislature'],
    'bare representative at state level' => ['Representative', 'State Legislature'],
    'unknown office keeps its own title' => ['railroad commissioner', 'Railroad commissioner'],
    'no title' => [null, 'Other Statewide'],
    'blank title' => ['   ', 'Other Statewide'],
]);

it('has a plain-language description for every named group', function () {
    $roles = OfficeGlossary::statewideRoles();

    foreach (['Insurance Commissioner', 'Superintendent of Public Instruction', 'State Auditor', 'Agriculture Commissioner',
        'Labor Commissioner', 'Land Commissioner', 'Board of Equalization', 'State Legislature'] as $group) {
        expect($roles[$group] ?? '')->not->toBe('');
    }
});

it('keeps the map panel descriptions in step with the glossary groups', function () {
    $js = file_get_contents(resource_path('js/map/config/constants.js'));

    foreach (array_keys(OfficeGlossary::statewideRoles()) as $group) {
        expect($js)->toContain("'{$group}':");
    }
});
