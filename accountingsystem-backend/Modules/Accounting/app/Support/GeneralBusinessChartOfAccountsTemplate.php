<?php

namespace Modules\Accounting\Support;

final class GeneralBusinessChartOfAccountsTemplate
{
    /**
     * Canonical COA list from product spec.
     * Redundant/invalid rows (e.g. VAT without code) are intentionally excluded.
     *
     * @return list<array<string, mixed>>
     */
    public static function entries(): array
    {
        return [
            ['code' => '1000', 'name' => 'Application of Funds (Assets)', 'type' => 'asset', 'parent_code' => null, 'is_posting' => false],
            ['code' => '1100-1600', 'name' => 'Current Assets', 'type' => 'asset', 'parent_code' => '1000', 'is_posting' => false, 'account_type' => 'Current Assets'],
            ['code' => '1100', 'name' => 'Cash In Hand', 'type' => 'asset', 'parent_code' => '1100-1600', 'is_posting' => false, 'account_type' => 'Current Assets', 'detail_type' => 'Cash In Hand'],
            ['code' => '1110', 'name' => 'Cash', 'type' => 'asset', 'parent_code' => '1100', 'is_posting' => true, 'account_type' => 'Current Assets', 'detail_type' => 'Cash In Hand'],
            ['code' => '1200', 'name' => 'Bank Accounts', 'type' => 'asset', 'parent_code' => '1100-1600', 'is_posting' => false, 'account_type' => 'Current Assets', 'detail_type' => 'Bank Accounts'],
            // Default posting bank account so newly-onboarded companies can
            // accept bank/check receipts without first hand-crafting a CoA
            // row. The company can rename this (e.g. "BDO Checking — 1234")
            // or add siblings under 1200 for additional bank accounts.
            ['code' => '1210', 'name' => 'Bank Account', 'type' => 'asset', 'parent_code' => '1200', 'is_posting' => true, 'account_type' => 'Current Assets', 'detail_type' => 'Bank Accounts'],
            // Single generic posting account for digital-wallet receipts. The
            // specific provider (GCash / Maya / GrabPay / ShopeePay / Coins.ph)
            // is captured on each payment row in `payment_channel`, NOT as a
            // separate CoA entry — keeps the chart compact while still letting
            // reports filter or group by provider.
            ['code' => '1230', 'name' => 'Digital Wallet', 'type' => 'asset', 'parent_code' => '1200', 'is_posting' => true, 'account_type' => 'Current Assets', 'detail_type' => 'Bank Accounts'],
            ['code' => '1300', 'name' => 'Accounts Receivable', 'type' => 'asset', 'parent_code' => '1100-1600', 'is_posting' => false, 'account_type' => 'Current Assets', 'detail_type' => 'Accounts Receivable'],
            ['code' => '1310', 'name' => 'Sales Receivable', 'type' => 'asset', 'parent_code' => '1300', 'is_posting' => true, 'account_type' => 'Current Assets', 'detail_type' => 'Accounts Receivable'],
            ['code' => '1400', 'name' => 'Stock Assets', 'type' => 'asset', 'parent_code' => '1100-1600', 'is_posting' => false, 'account_type' => 'Current Assets', 'detail_type' => 'Stock Assets'],
            ['code' => '1410', 'name' => 'Stock In Hand', 'type' => 'asset', 'parent_code' => '1400', 'is_posting' => true, 'account_type' => 'Current Assets', 'detail_type' => 'Stock Assets'],
            ['code' => '1500', 'name' => 'Tax Assets', 'type' => 'asset', 'parent_code' => '1100-1600', 'is_posting' => false, 'account_type' => 'Current Assets', 'detail_type' => 'Tax Assets'],
            ['code' => '1510', 'name' => 'Input VAT', 'type' => 'asset', 'parent_code' => '1500', 'is_posting' => true, 'account_type' => 'Current Assets', 'detail_type' => 'Tax Assets'],
            ['code' => '1520', 'name' => 'Withholding Tax Receivable', 'type' => 'asset', 'parent_code' => '1500', 'is_posting' => true, 'account_type' => 'Current Assets', 'detail_type' => 'Tax Assets'],
            ['code' => '1600', 'name' => 'Loans and Advances (Assets)', 'type' => 'asset', 'parent_code' => '1100-1600', 'is_posting' => false, 'account_type' => 'Current Assets', 'detail_type' => 'Loans and Advances (Assets)'],
            ['code' => '1610', 'name' => 'Employee Advances', 'type' => 'asset', 'parent_code' => '1600', 'is_posting' => true, 'account_type' => 'Current Assets', 'detail_type' => 'Loans and Advances (Assets)'],
            ['code' => '1650', 'name' => 'Securities and Deposits', 'type' => 'asset', 'parent_code' => '1100-1600', 'is_posting' => false, 'account_type' => 'Current Assets', 'detail_type' => 'Securities and Deposits'],
            ['code' => '1651', 'name' => 'Earnest Money', 'type' => 'asset', 'parent_code' => '1650', 'is_posting' => true, 'account_type' => 'Current Assets', 'detail_type' => 'Securities and Deposits'],
            ['code' => '1660', 'name' => 'Prepaid Expenses', 'type' => 'asset', 'parent_code' => '1100-1600', 'is_posting' => true, 'account_type' => 'Current Assets', 'detail_type' => 'Prepaid Expenses'],
            ['code' => '1670', 'name' => 'Short-term Investments', 'type' => 'asset', 'parent_code' => '1100-1600', 'is_posting' => true, 'account_type' => 'Current Assets', 'detail_type' => 'Short-term Investments'],
            ['code' => '1700', 'name' => 'Fixed Assets', 'type' => 'asset', 'parent_code' => '1000', 'is_posting' => false, 'account_type' => 'Fixed Assets'],
            ['code' => '1710', 'name' => 'Capital Equipment', 'type' => 'asset', 'parent_code' => '1700', 'is_posting' => true, 'account_type' => 'Fixed Assets', 'detail_type' => 'Capital Equipment'],
            ['code' => '1720', 'name' => 'Electronic Equipment', 'type' => 'asset', 'parent_code' => '1700', 'is_posting' => true, 'account_type' => 'Fixed Assets', 'detail_type' => 'Electronic Equipment'],
            ['code' => '1730', 'name' => 'Furniture and Fixtures', 'type' => 'asset', 'parent_code' => '1700', 'is_posting' => true, 'account_type' => 'Fixed Assets', 'detail_type' => 'Furniture and Fixtures'],
            ['code' => '1740', 'name' => 'Office Equipment', 'type' => 'asset', 'parent_code' => '1700', 'is_posting' => true, 'account_type' => 'Fixed Assets', 'detail_type' => 'Office Equipment'],
            ['code' => '1750', 'name' => 'Plants and Machineries', 'type' => 'asset', 'parent_code' => '1700', 'is_posting' => true, 'account_type' => 'Fixed Assets', 'detail_type' => 'Plants and Machineries'],
            ['code' => '1760', 'name' => 'Buildings', 'type' => 'asset', 'parent_code' => '1700', 'is_posting' => true, 'account_type' => 'Fixed Assets', 'detail_type' => 'Buildings'],
            ['code' => '1770', 'name' => 'Software', 'type' => 'asset', 'parent_code' => '1700', 'is_posting' => true, 'account_type' => 'Fixed Assets', 'detail_type' => 'Software'],
            ['code' => '1780', 'name' => 'Accumulated Depreciation', 'type' => 'asset', 'parent_code' => '1700', 'is_posting' => true, 'account_type' => 'Fixed Assets', 'detail_type' => 'Accumulated Depreciation'],
            ['code' => '1790', 'name' => 'CWIP Account', 'type' => 'asset', 'parent_code' => '1700', 'is_posting' => true, 'account_type' => 'Fixed Assets', 'detail_type' => 'CWIP Account'],
            ['code' => '1800', 'name' => 'Investments', 'type' => 'asset', 'parent_code' => '1000', 'is_posting' => false, 'account_type' => 'Investments', 'detail_type' => 'Investments'],
            ['code' => '1900', 'name' => 'Temporary Accounts', 'type' => 'asset', 'parent_code' => '1000', 'is_posting' => false, 'account_type' => 'Temporary Accounts'],
            ['code' => '1910', 'name' => 'Temporary Opening', 'type' => 'asset', 'parent_code' => '1900', 'is_posting' => true, 'account_type' => 'Temporary Accounts', 'detail_type' => 'Temporary Opening'],

            ['code' => '2000', 'name' => 'Source of Funds (Liabilities)', 'type' => 'liability', 'parent_code' => null, 'is_posting' => false],
            ['code' => '2100-2400', 'name' => 'Current Liabilities', 'type' => 'liability', 'parent_code' => '2000', 'is_posting' => false, 'account_type' => 'Current Liabilities'],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'parent_code' => '2100-2400', 'is_posting' => false, 'account_type' => 'Current Liabilities', 'detail_type' => 'Accounts Payable'],
            ['code' => '2110', 'name' => 'Creditors', 'type' => 'liability', 'parent_code' => '2100', 'is_posting' => true, 'account_type' => 'Current Liabilities', 'detail_type' => 'Accounts Payable'],
            ['code' => '2120', 'name' => 'Payroll Payable', 'type' => 'liability', 'parent_code' => '2100', 'is_posting' => true, 'account_type' => 'Current Liabilities', 'detail_type' => 'Accounts Payable'],
            ['code' => '2150', 'name' => 'Accrued Expenses', 'type' => 'liability', 'parent_code' => '2100-2400', 'is_posting' => true, 'account_type' => 'Current Liabilities', 'detail_type' => 'Accrued Expenses'],
            ['code' => '2160', 'name' => 'Customer Advances', 'type' => 'liability', 'parent_code' => '2100-2400', 'is_posting' => true, 'account_type' => 'Current Liabilities', 'detail_type' => 'Customer Advances'],
            ['code' => '2200', 'name' => 'Stock Liabilities', 'type' => 'liability', 'parent_code' => '2100-2400', 'is_posting' => false, 'account_type' => 'Current Liabilities', 'detail_type' => 'Stock Liabilities'],
            ['code' => '2210', 'name' => 'Stock Received But Not Billed', 'type' => 'liability', 'parent_code' => '2200', 'is_posting' => true, 'account_type' => 'Current Liabilities', 'detail_type' => 'Stock Liabilities'],
            ['code' => '2211', 'name' => 'Asset Received But Not Billed', 'type' => 'liability', 'parent_code' => '2200', 'is_posting' => true, 'account_type' => 'Current Liabilities', 'detail_type' => 'Stock Liabilities'],
            ['code' => '2300', 'name' => 'Duties and Taxes', 'type' => 'liability', 'parent_code' => '2100-2400', 'is_posting' => false, 'account_type' => 'Current Liabilities', 'detail_type' => 'Duties and Taxes'],
            ['code' => '2310', 'name' => 'Withholding Tax Payable', 'type' => 'liability', 'parent_code' => '2300', 'is_posting' => true, 'account_type' => 'Current Liabilities', 'detail_type' => 'Duties and Taxes'],
            ['code' => '2311', 'name' => 'Sales Tax Payable', 'type' => 'liability', 'parent_code' => '2300', 'is_posting' => true, 'account_type' => 'Current Liabilities', 'detail_type' => 'Duties and Taxes'],
            ['code' => '2312', 'name' => 'VAT Payable', 'type' => 'liability', 'parent_code' => '2300', 'is_posting' => true, 'account_type' => 'Current Liabilities', 'detail_type' => 'Duties and Taxes'],
            ['code' => '2350', 'name' => 'Short-term Provisions', 'type' => 'liability', 'parent_code' => '2100-2400', 'is_posting' => true, 'account_type' => 'Current Liabilities', 'detail_type' => 'Short-term Provisions'],
            ['code' => '2400', 'name' => 'Loans (Liabilities)', 'type' => 'liability', 'parent_code' => '2100-2400', 'is_posting' => false, 'account_type' => 'Current Liabilities', 'detail_type' => 'Loans (Liabilities)'],
            ['code' => '2410', 'name' => 'Secured Loans', 'type' => 'liability', 'parent_code' => '2400', 'is_posting' => true, 'account_type' => 'Current Liabilities', 'detail_type' => 'Loans (Liabilities)'],
            ['code' => '2420', 'name' => 'Unsecured Loans', 'type' => 'liability', 'parent_code' => '2400', 'is_posting' => true, 'account_type' => 'Current Liabilities', 'detail_type' => 'Loans (Liabilities)'],
            ['code' => '2430', 'name' => 'Bank Overdraft Account', 'type' => 'liability', 'parent_code' => '2400', 'is_posting' => true, 'account_type' => 'Current Liabilities', 'detail_type' => 'Loans (Liabilities)'],
            ['code' => '2500', 'name' => 'Non-Current Liabilities', 'type' => 'liability', 'parent_code' => '2000', 'is_posting' => false, 'account_type' => 'Non-Current Liabilities'],
            ['code' => '2510', 'name' => 'Long-term Provisions', 'type' => 'liability', 'parent_code' => '2500', 'is_posting' => true, 'account_type' => 'Non-Current Liabilities', 'detail_type' => 'Long-term Provisions'],
            ['code' => '2520', 'name' => 'Employee Benefits Obligation', 'type' => 'liability', 'parent_code' => '2500', 'is_posting' => true, 'account_type' => 'Non-Current Liabilities', 'detail_type' => 'Employee Benefits Obligation'],

            ['code' => '3000', 'name' => 'Equity', 'type' => 'equity', 'parent_code' => null, 'is_posting' => false, 'account_type' => 'Equity'],
            ['code' => '3100', 'name' => 'Capital Stock', 'type' => 'equity', 'parent_code' => '3000', 'is_posting' => true, 'account_type' => 'Equity', 'detail_type' => 'Capital Stock'],
            ['code' => '3200', 'name' => 'Dividends Paid', 'type' => 'equity', 'parent_code' => '3000', 'is_posting' => true, 'account_type' => 'Equity', 'detail_type' => 'Dividends Paid'],
            ['code' => '3300', 'name' => 'Opening Balance Equity', 'type' => 'equity', 'parent_code' => '3000', 'is_posting' => true, 'account_type' => 'Equity', 'detail_type' => 'Opening Balance Equity'],
            ['code' => '3400', 'name' => 'Retained Earnings', 'type' => 'equity', 'parent_code' => '3000', 'is_posting' => true, 'account_type' => 'Equity', 'detail_type' => 'Retained Earnings'],
            ['code' => '3500', 'name' => 'Revaluation Surplus', 'type' => 'equity', 'parent_code' => '3000', 'is_posting' => true, 'account_type' => 'Equity', 'detail_type' => 'Revaluation Surplus'],

            ['code' => '4000', 'name' => 'Income', 'type' => 'revenue', 'parent_code' => null, 'is_posting' => false],
            ['code' => '4100', 'name' => 'Direct Income', 'type' => 'revenue', 'parent_code' => '4000', 'is_posting' => false, 'account_type' => 'Direct Income'],
            ['code' => '4110', 'name' => 'Sales', 'type' => 'revenue', 'parent_code' => '4100', 'is_posting' => true, 'account_type' => 'Direct Income', 'detail_type' => 'Sales'],
            ['code' => '4120', 'name' => 'Service', 'type' => 'revenue', 'parent_code' => '4100', 'is_posting' => true, 'account_type' => 'Direct Income', 'detail_type' => 'Service'],
            ['code' => '4200', 'name' => 'Indirect Income', 'type' => 'revenue', 'parent_code' => '4000', 'is_posting' => false, 'account_type' => 'Indirect Income'],
            ['code' => '4210', 'name' => 'Interest Income', 'type' => 'revenue', 'parent_code' => '4200', 'is_posting' => true, 'account_type' => 'Indirect Income', 'detail_type' => 'Interest Income'],
            ['code' => '4220', 'name' => 'Interest on Fixed Deposits', 'type' => 'revenue', 'parent_code' => '4200', 'is_posting' => true, 'account_type' => 'Indirect Income', 'detail_type' => 'Interest on Fixed Deposits'],

            ['code' => '5000', 'name' => 'Expenses', 'type' => 'expense', 'parent_code' => null, 'is_posting' => false],
            ['code' => '5100', 'name' => 'Direct Expenses', 'type' => 'expense', 'parent_code' => '5000', 'is_posting' => false, 'account_type' => 'Direct Expenses'],
            ['code' => '5110', 'name' => 'Stock Expenses', 'type' => 'expense', 'parent_code' => '5100', 'is_posting' => false, 'account_type' => 'Direct Expenses', 'detail_type' => 'Stock Expenses'],
            ['code' => '5111', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'parent_code' => '5110', 'is_posting' => true, 'account_type' => 'Direct Expenses', 'detail_type' => 'Stock Expenses'],
            ['code' => '5112', 'name' => 'Expenses Included In Asset Valuation', 'type' => 'expense', 'parent_code' => '5110', 'is_posting' => true, 'account_type' => 'Direct Expenses', 'detail_type' => 'Stock Expenses'],
            ['code' => '5118', 'name' => 'Expenses Included In Valuation', 'type' => 'expense', 'parent_code' => '5110', 'is_posting' => true, 'account_type' => 'Direct Expenses', 'detail_type' => 'Stock Expenses'],
            ['code' => '5119', 'name' => 'Stock Adjustment', 'type' => 'expense', 'parent_code' => '5110', 'is_posting' => true, 'account_type' => 'Direct Expenses', 'detail_type' => 'Stock Expenses'],
            ['code' => '5200', 'name' => 'Indirect Expenses', 'type' => 'expense', 'parent_code' => '5000', 'is_posting' => false, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5201', 'name' => 'Administrative Expenses', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5202', 'name' => 'Commission on Sales', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5203', 'name' => 'Depreciation', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5204', 'name' => 'Entertainment Expenses', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5205', 'name' => 'Freight and Forwarding Charges', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5206', 'name' => 'Legal Expenses', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5207', 'name' => 'Marketing Expenses', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5208', 'name' => 'Office Maintenance Expenses', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5209', 'name' => 'Office Rent', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5210', 'name' => 'Postal Expenses', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5211', 'name' => 'Print and Stationery', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5212', 'name' => 'Round Off', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5213', 'name' => 'Salary', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5214', 'name' => 'Sales Expenses', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5215', 'name' => 'Telephone Expenses', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5216', 'name' => 'Travel Expenses', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5217', 'name' => 'Utility Expenses', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5218', 'name' => 'Write Off', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5219', 'name' => 'Exchange Gain/Loss', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5220', 'name' => 'Interest Expense', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5221', 'name' => 'Bank Charges', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5222', 'name' => 'Gain/Loss on Asset Disposal', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5223', 'name' => 'Miscellaneous Expenses', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5224', 'name' => 'Impairment', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
            ['code' => '5225', 'name' => 'Tax Expense', 'type' => 'expense', 'parent_code' => '5200', 'is_posting' => true, 'account_type' => 'Indirect Expenses', 'detail_type' => 'Indirect Expenses'],
        ];
    }
}
