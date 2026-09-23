<?php

namespace App\Support\Concerns;

/**
 * Expands a bare district number into the string variants candidate/office
 * data is actually stored under ("12" -> "District 12", "CD-12", "CA-12", …).
 *
 * Copied from PublicProfileController::districtVariants() rather than
 * extracted into a shared call site — that controller is large and
 * unrelated to the portal feature, and this is a small, pure, already-
 * stable helper, so duplicating it here is lower risk than refactoring it.
 */
trait ResolvesDistrictVariants
{
    protected function districtVariants(string $state, string $districtNumber): array
    {
        $districtNumber = strtoupper(trim($districtNumber));

        if ($districtNumber !== 'AL') {
            $numeric = (string) ((int) $districtNumber);
            $padded = str_pad($numeric, 2, '0', STR_PAD_LEFT);

            $variants = [
                $numeric,
                $padded,
                'District '.$numeric,
                'CD '.$numeric,
                'CD-'.$numeric,
            ];

            if ($state !== '') {
                $variants[] = $state.'-'.$padded;
                $variants[] = $state.'-'.$numeric;
            }
        } else {
            $variants = ['At-Large', 'At Large'];

            if ($state !== '') {
                $variants[] = $state.'-AL';
            }
        }

        return array_values(array_unique($variants));
    }
}
