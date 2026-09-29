<?php

namespace App\Domain\Policies\Importing;

/**
 * Reads the Policies, Policy votes, Display notes and Divisions tabs of the
 * curation workbook, matching columns by their heading so reviewers can add
 * or move columns without breaking the import.
 */
class PolicyWorkbookReader
{
    public function __construct(private XlsxReader $xlsx) {}

    public function read(string $path): PolicyWorkbook
    {
        $errors = [];
        $tabs = [];
        $sheets = $this->xlsx->sheetNames($path);

        foreach (PolicyWorkbook::REQUIRED_HEADINGS as $sheet => $required) {
            if (! in_array($sheet, $sheets, true)) {
                $errors[] = "The workbook has no [{$sheet}] tab.";

                continue;
            }

            $rows = $this->xlsx->rows($path, $sheet);
            $headings = array_map(trim(...), $rows[1] ?? []);

            foreach (array_diff($required, $headings) as $missing) {
                $errors[] = "{$sheet} tab: missing the column [{$missing}].";
            }

            $tabs[$sheet] = $this->keyByHeading(array_diff_key($rows, [1 => true]), $headings);
        }

        if ($errors !== []) {
            throw new InvalidPolicyWorkbook($errors);
        }

        return new PolicyWorkbook(
            $tabs[PolicyWorkbook::POLICIES],
            $tabs[PolicyWorkbook::POLICY_VOTES],
            $tabs[PolicyWorkbook::DIVISIONS],
            $tabs[PolicyWorkbook::DISPLAY_NOTES],
        );
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, string>  $headings
     * @return array<int, array<string, string>>
     */
    private function keyByHeading(array $rows, array $headings): array
    {
        $keyed = [];

        foreach ($rows as $number => $cells) {
            $row = [];

            foreach ($headings as $column => $heading) {
                if ($heading !== '') {
                    $row[$heading] = trim($cells[$column] ?? '');
                }
            }

            if (implode('', $row) !== '') {
                $keyed[$number] = $row;
            }
        }

        return $keyed;
    }
}
