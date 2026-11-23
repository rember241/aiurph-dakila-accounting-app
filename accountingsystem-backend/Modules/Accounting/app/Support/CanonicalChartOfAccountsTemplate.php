<?php

namespace Modules\Accounting\Support;

use RuntimeException;

final class CanonicalChartOfAccountsTemplate
{
    /**
     * Chart of accounts transcribed from COA CHURCH AND FARM.xlsx.
     *
     * @return list<array<string, mixed>>
     */
    public static function entries(): array
    {
        $entries = [];

        foreach (preg_split('/\R/', trim(self::sourceRows())) ?: [] as $row) {
            [$code, $name] = explode('|', $row, 2);
            $metadata = self::metadataForCode((int) $code);

            $entries[] = [
                'code' => $code,
                'name' => $name,
                'type' => $metadata['type'],
                'parent_code' => null,
                'is_posting' => true,
                'is_contra' => (int) $code >= 6000 && (int) $code <= 6014,
                'account_type' => $metadata['account_type'],
                'detail_type' => $metadata['detail_type'],
                'description' => self::descriptionForCode($code),
                'contra_account_code' => self::contraAccountCode($code),
            ];
        }

        return $entries;
    }

    /**
     * @return array{type: string, account_type: string, detail_type: string}
     */
    private static function metadataForCode(int $code): array
    {
        return match (true) {
            $code >= 1100 && $code <= 1110 => self::metadata('asset', 'Assets', 'Cash on hand and in banks'),
            $code >= 2000 && $code <= 2012 => self::metadata('asset', 'Assets', 'Receivables'),
            $code >= 3000 && $code <= 3006 => self::metadata('asset', 'Assets', 'Prepayments and Other Current Assets'),
            $code >= 4000 && $code <= 4001 => self::metadata('asset', 'Assets', 'Advances'),
            $code >= 5000 && $code <= 5014 => self::metadata('asset', 'Assets', 'Property, Plant, and Equipment'),
            $code >= 6000 && $code <= 6014 => self::metadata('asset', 'Assets', 'Accumulated Depreciation'),
            $code >= 7000 && $code <= 7015 => self::metadata('liability', 'Liabilities', 'Payables'),
            $code === 8000 => self::metadata('liability', 'Liabilities', 'Deferred Income'),
            $code === 9000 => self::metadata('liability', 'Liabilities', 'Deposit'),
            $code >= 10000 && $code <= 10002 => self::metadata('equity', 'Equity', 'Retained Earnings'),
            $code >= 11000 && $code <= 11004 => self::metadata('equity', 'Equity', 'Reserves and Fund Balances'),
            $code >= 12000 && $code <= 12023 => self::metadata('revenue', 'Income/Revenue', 'Income/Revenue'),
            $code >= 13000 && $code <= 13133 => self::metadata('expense', 'Expenses', 'Expenses'),
            default => throw new RuntimeException("Unsupported workbook account code [{$code}]."),
        };
    }

    /**
     * @return array{type: string, account_type: string, detail_type: string}
     */
    private static function metadata(string $type, string $accountType, string $detailType): array
    {
        return [
            'type' => $type,
            'account_type' => $accountType,
            'detail_type' => $detailType,
        ];
    }

    private static function descriptionForCode(string $code): ?string
    {
        $categories = [
            '1100' => 'Cash in Bank',
            '1104' => 'Cash in Bank',
            '1105' => 'Cash on Hand',
            '1106' => 'Cash on Hand',
            '1107' => 'Cash on Hand',
            '1108' => 'Cash in Bank',
            '1110' => 'Cash in Bank',
            '13033' => 'Energy Cost',
            '13036' => 'Energy Cost',
            '13051' => 'Supplies Expense',
        ];

        if ((int) $code >= 2000 && (int) $code <= 2012) {
            return 'Workbook account category: Accounts Receivable';
        }

        if ((int) $code >= 3000 && (int) $code <= 3006) {
            return 'Workbook account category: Prepayments, Other Current Assets';
        }

        if ((int) $code >= 4000 && (int) $code <= 4001) {
            return 'Workbook account category: Advances, Other Current Assets';
        }

        return isset($categories[$code])
            ? 'Workbook account category: '.$categories[$code]
            : null;
    }

    private static function contraAccountCode(string $code): ?string
    {
        return [
            '6000' => '5000',
            '6001' => '5002',
            '6002' => '5003',
            '6003' => '5004',
            '6004' => '5005',
            '6007' => '5006',
            '6008' => '5007',
            '6009' => '5012',
            '6010' => '5008',
            '6011' => '5009',
            '6012' => '5010',
            '6013' => '5011',
            '6014' => '5013',
        ][$code] ?? null;
    }

    private static function sourceRows(): string
    {
        return <<<'COA'
1100|CIB-PNB General Fund Account
1101|CIB-PNB Dollar Account
1102|CIB-BPI Family Columbarium Account
1103|CIB-BPI Use of Buildings Account
1104|CIB-PNB Multipurpose Building Fund Account
1105|Cash-Collecting Officer
1106|Petty Cash Fund
1107|Revolving Fund
1108|CIB-PNB Egov Account
1109|Cash on Hand-Dollar Account
1110|CIB-PNB Time Deposit Account
2000|Loans Receivable, EDNCP
2001|Accounts Receivable, EDNCP
2002|Accounts Receivable, Employees
2003|Accounts Receivable, Social Security System (SSS)
2004|Accounts Receivable, Philhealth
2005|Accounts Receivable, Pag-ibig
2006|Accounts Receivable, General Fund Account
2007|Accounts Receivable, Multipurpose Building Fund Account
2008|Accounts Receivable, Dollar Account
2009|Accounts Receivable, Egov Account
2010|Accounts Receivable, Time Deposit Account
2011|Accrued Rent Receivables
2012|Allowance for Bad Debts
3000|Prepaid Taxes
3001|Prepaid Vat
3002|Prepaid Rent
3003|Prepaid Expenses
3004|Input Vat
3006|Creditable Withholding Taxes
4000|Cash Advance
4001|Advances to Employees
5000|Furniture and Fixtures
5001|Furniture and Fixtures-Use of Buildings
5002|Furniture and Fixtures-Sunday School
5003|Furnishings-Rectory
5004|CCTV
5005|Computer Equipment
5006|Signages
5007|Church Building
5008|Building-Rectory
5009|Building-Twin Chapels
5010|Building-Lower and Upper Hall
5011|Building-Sunday School
5012|Vehicle Van
5013|Fr. Alejandrino Rulite Multipurpose Building
5014|CIP FARM Building-3rd Floor
6000|Accumulated Depreciation-Furniture and Fixtures
6001|Accumulated Depreciation-Furniture and Fixtures-Sunday School
6002|Accumulated Depreciation-Furnishings-Rectory
6003|Accumulated Depreciation-CCTV
6004|Accumulated Depreciation-Computer Equipment
6005|Accumulated Depreciation-Cistern tank
6006|Accumulated Depreciation-Storage Room and Basement Stairs
6007|Accumulated Depreciation-Signages
6008|Accumulated Depreciation-Church Building
6009|Accumulated Depreciation-Vehicle Van
6010|Accumulated Depreciation-Building-Rectory
6011|Accumulated Depreciation-Building-Twin Chapels
6012|Accumulated Depreciation-Building-Lower and Upper Hall
6013|Accumulated Depreciation-Building-Sunday School
6014|Accumulated Depreciation-Fr. Alejandrino Rulite Multipurpose Building
7000|Accounts Payable, EDNCP
7001|Accounts Payable, Employees
7002|SSS Premiums Payable
7003|Philhealth Premiums Payable
7004|Pag-ibig Premiums Payable
7005|Pag-ibig-MP2 Premiums Payable
7006|Accounts Payable, General Fund Account
7007|Accounts Payable, Multipurpose Building Fund Account
7008|Accounts Payable, Dollar Account
7009|Accounts Payable, Egov Account
7010|Accounts Payable, Time Deposit Account
7011|Accounts Payable, EDNCP Credit Cooperative
7012|Accounts Payable, Clericus/Holy Land
7013|Accounts Payable, Metro Baguio Deanery-Sacramental Tree Planting
7014|Expanded Withholding Taxes
7015|Output Vat
8000|Deferred Income
9000|Security Deposit
10000|Retained Earnings/Institutional Fund
10001|Profit / Loss
10002|Net Savings (Deficit)
11000|RE Appropriated - Endowment Fund (Reserved for Contigencies)
11001|RE Appropriated - Building-Rectory Fund
11002|RE Appropriated - Painting Fund
11003|RE Appropriated - Columbarium Repair Fund
11004|RE Appropriated - Multipurpose Building Fund
12000|Loose Alms
12001|Envelopes-Pledges/Special Offerings/Various Occasions
12002|Tithes
12003|Baptism
12004|Burial and Requiem
12005|Wedding
12006|Certificates
12007|Blessings
12008|Sunday Market
12009|Miscellaneous Donations/Income
12010|Miscellaneous Donations/Income-FARM Building
12011|Old Rulite Building Rental Income
12012|Upper and Lower Hall
12013|Twin Chapels
12014|Livestream Donations
12015|Interest Income
12016|Interest Income-Use of Buildings
12017|Interest Income-FARM Building
12018|Farm Building Rental Income
12019|Penalties
12020|Tickets
12021|Mission
12022|Mural
12023|Columbarium
13000|Salaries and Wages
13001|Salaries and Wages-Clergies
13002|Salaries and Wages-Use of Buildings
13003|Salaries and Wages-FARM Building
13004|13th Month Pay
13005|13th Month Pay-Use of Buildings
13006|13th Month Pay-FARM Building
13007|Employer Share-Government Mandated Benefits
13008|Employer Share-Government Mandated Benefits-Use of Buildings
13009|Employer Share-Government Mandated Benefits-FARM Building
13010|Employee Christmas Bonus
13011|Employee Christmas Bonus-Use of Buildings
13012|Employee Christmas Bonus-FARM Building
13013|ECP Pension (15% of total basic salary + longevity)
13014|ECP Pension (15% of total basic salary + longevity)-Use of Buildings
13015|ECP Pension (15% of total basic salary + longevity)-FARM Building
13016|Christmas Bonus-Honoraria Staff
13017|Support to Sister Satty
13018|Employee Uniforms
13019|Employee Uniforms-Use of Buildings
13020|Employee Uniforms-FARM Building
13021|Employee Incentives
13022|Employee Incentives-Use of Buildings
13023|Employee Incentives-FARM Building
13024|Representation Expense for Church Guests
13025|Medical Benefit-Dean and Rector
13026|Medical Benefit-Curate/Assisting Priest
13027|Transportation Allowance-Curate/Assisting Priest
13028|Transportation-Substitute Priest and Intern
13029|Vestment/Liturgical Garments
13030|Security Services
13031|Security Services-FARM Building
13032|Church Supplies
13033|Power/Electricity
13034|Power/Electricity-Use of Buildings
13035|Power/Electricity-FARM Building
13036|Water
13037|Water-Use of Buildings
13038|Water-FARM Building
13039|Special Events-Week of Prayer
13040|Special Events-Easter Sunday
13041|Special Events-Easter Lessons and Carols
13042|Special Events-Chapter Planning Session
13043|Special Events-Chapter Family Day
13044|Special Events-Chapter Luncheon
13045|Special Events-Indigenous People's Month
13046|Special Events-Christmas Goodies for Children
13047|Special Events-Christmas Lessons and Carols
13048|Special Events-Christmas Dinner
13049|Special Events-Simbang Gabi/Misa De Gallo
13050|Special Events-Contingency
13051|Office Supplies
13052|Office Supplies-FARM Building
13053|Meeting-Chapter and Committee
13054|Meeting-Chapter and Committee-FARM Building
13055|Christmas Gifts-Chapter and Cash Counters
13056|Christmas Gifts-Chapter and Cash Counters-FARM Building
13057|Kitchen Equipment
13058|Repairs and Maintenance-Church
13059|Repairs and Maintenance-Twin Chapels
13060|Repairs and Maintenance-Lower and Upper Hall
13061|Repairs and Maintenance-Alejandrino Rulite Building
13062|Repairs and Maintenance-Old Rulite Building
13063|Repairs and Maintenance-Gate Area
13064|Repairs and Maintenance-Columbarium
13065|Repairs and Maintenance-Elevator
13066|Repairs and Maintenance-Admin Office
13067|Repairs and Maintenance-Choir Loft
13068|Generator Maintenance
13069|Repairs and Maintenance-Others
13070|Repairs and Maintenance-Sound System
13071|Repairs and Maintenance-Kitchen and Coffee Area
13072|Grounds and Landscaping
13073|Sewage and Water Treatment
13074|Painting
13075|CCTV Preventive Maintenance
13076|Lighting Fixtures
13077|Lighting Fixtures-Use of Buildings
13078|Lighting Fixtures-FARM Building
13079|Laundry and Janitorial
13080|Laundry and Janitorial-Use of Buildings
13081|Laundry and Janitorial-FARM Building
13082|Vehicle Van Maintenance
13083|PLDT and Communication
13084|Legal Fees
13085|Legal Fees-FARM Building
13086|Professional Fees
13087|Professional Fees-FARM Building
13088|Consultancy Fees
13089|Consultancy Fees-FARM Building
13090|Dues and Subscription
13091|Dues and Subscription-FARM Building
13092|Miscellaneous Expense
13093|Miscellaneous Expense-FARM Building
13094|Bank and Service Charges
13095|Bank and Service Charges-FARM Building
13096|Printing and Publication
13097|Printing and Publication-FARM Building
13098|Transportation Expense
13099|Transportation Expense-FARM Building
13100|Audit Fee Expense
13101|Audit Fee Expense-FARM Building
13102|Bishop's Burse
13103|Coffee Hour
13104|Apportionment
13105|Church Donations
13106|Mandated Designated Offerings
13107|Music Ministry
13108|Personnel Development
13109|Livestream Expense
13110|Christian Education and Lay Ministry
13111|Evangelism and Stewardship
13112|Sunday School
13113|SKEP
13114|Support to Convention and Deanery
13115|Support to Local Churches
13116|Depreciation Expense
13117|Depreciation Expense-Use of Buildings
13118|Depreciation Expense-FARM Building
13119|Postage and Freight
13120|Postage and Freight-FARM Building
13121|LPG
13122|Corporate Income Tax
13123|Output VAT
13124|Input VAT
13125|Taxes and Licenses-Business Permit
13126|Taxes-Others
13127|Diesel and Fuel
13128|Clergy Withheld Tithes
13129|Insurance
13130|Outside of Cathedral Use
13131|Educational Assistance to Seminarians
13132|Support to EDNCP Clergy Development
13133|Mission Work
COA;
    }
}
