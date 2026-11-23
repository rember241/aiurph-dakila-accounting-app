<!doctype html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport"
              content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Balance Sheet Report</title>

        <style>
            /* PDF settings */
            @page {
                margin: 72pt; /* 1 inch margin on all sides of the paper */
            }

            body {
                font-family: 'DejaVu Sans', sans-serif;
                font-size: 12px;
            }

            body, h3, p {
                margin: 0;
                padding: 0;
            }

            header {
                position: fixed;
                top: -59pt;
                left: 0;
                right: 0;
                text-align: center;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                border-spacing: 1px;
                margin: 0;
            }

            th {
                background-color: black;
                color: white;
            }
        </style>
    </head>
    <body>
        {{-- Company Name and Date --}}
        <header>
            <h3>{{$companyName}}</h3>
            <p>Balance Sheet Report</p>
            <p>{{$dateText}}</p>
        </header>

        <main>
            {{-- Asset Table --}}
            <table style="margin-bottom: 25px">
                <thead>
                    <tr>
                        <th style="font-weight: bold; text-align: left">ASSETS</th>
                        @foreach($labels as $dateLabel)
                            <th style="font-weight: bold; text-align: right; min-width: 170px">
                                {{ $dateLabel }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    {{-- Current Assets --}}
                    <tr>
                        <td colspan="{{ count($labels) + 1 }}" style="font-weight: bold; border-bottom: 2px solid black; width: 100%">Current Assets</td>
                    </tr>
                    @foreach($currentAssets as $account)
                        <tr>
                            <td style="padding-left: 16px">{{$account['name']}}</td>
                            <td style="text-align: right">
                                {{
                                    $account['balance'] >= 0
                                        ? Number::currency($account['balance'], in: 'PHP', locale: 'en_PH')
                                        : '(' . Number::currency(abs($account['balance']), in: 'PHP', locale: 'en_PH') . ')'
                                }}
                            </td>
                            @if(isset($account['previousBalances']))
                                @foreach($account['previousBalances'] as $previousBalance)
                                    <td style="text-align: right">
                                        {{
                                            $previousBalance >= 0
                                                ? Number::currency($previousBalance, in: 'PHP', locale: 'en_PH')
                                                : '(' . Number::currency(abs($previousBalance), in: 'PHP', locale: 'en_PH') . ')'
                                        }}
                                    </td>
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                    <tr>
                        <td style="font-weight: bold; border-top: 2px solid black">Total Current Assets</td>
                        @foreach($totalCurrentAssets as $currentAsset)
                            <td style="text-align: right; font-weight: bold; border-top: 2px solid black">
                                {{
                                    $currentAsset >= 0
                                        ? Number::currency($currentAsset, in: 'PHP', locale: 'en_PH')
                                        : '(' . Number::currency(abs($currentAsset), in: 'PHP', locale: 'en_PH') . ')'
                                }}
                            </td>
                        @endforeach
                    </tr>
                    <tr><td style="padding: 8px 0"></td></tr>

                    {{-- Non-current Assets --}}
                    <tr>
                        <td colspan="{{count($labels) + 1}}" style="font-weight: bold; border-bottom: 2px solid black;">Non-current Assets</td>
                    </tr>
                    @foreach($nonCurrentAssets as $account)
                        <tr>
                            <td style="padding-left: 16px">{{$account['name']}}</td>
                            <td style="text-align: right">
                                {{
                                    $account['balance'] >= 0
                                        ? Number::currency($account['balance'], in: 'PHP', locale: 'en_PH')
                                        : '(' . Number::currency(abs($account['balance']), in: 'PHP', locale: 'en_PH') . ')'
                                }}
                            </td>
                            @if(isset($account['previousBalances']))
                                @foreach($account['previousBalances'] as $previousBalance)
                                    <td style="text-align: right">
                                        {{
                                            $previousBalance >= 0
                                                ? Number::currency($previousBalance, in: 'PHP', locale: 'en_PH')
                                                : '(' . Number::currency(abs($previousBalance), in: 'PHP', locale: 'en_PH') . ')'
                                        }}
                                    </td>
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                    <tr>
                        <td style="font-weight: bold; border-top: 2px solid black">Total Non-current Assets</td>
                        @foreach($totalNonCurrentAssets as $nonCurrentAsset)
                            <td style="text-align: right; font-weight: bold; border-top: 2px solid black">
                                {{
                                    $nonCurrentAsset >= 0
                                        ? Number::currency($nonCurrentAsset, in: 'PHP', locale: 'en_PH')
                                        : '(' . Number::currency(abs($nonCurrentAsset), in: 'PHP', locale: 'en_PH') . ')'
                                }}
                            </td>
                        @endforeach
                    </tr>
                </tbody>

                <tfoot>
                    <tr>
                        <td style="font-weight: bold; border-top: 2px solid black; border-bottom: 2px solid black">Total Assets</td>
                        @foreach($totalAssets as $asset)
                            <td style="text-align: right; font-weight: bold; border-top: 2px solid black; border-bottom: 2px solid black">
                                {{
                                    $asset >= 0
                                        ? Number::currency($asset, in: 'PHP', locale: 'en_PH')
                                        : '(' . Number::currency(abs($asset), in: 'PHP', locale: 'en_PH') . ')'
                                }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>

            {{-- Liability Table --}}
            <table style="margin-bottom: 25px">
                <thead>
                <tr>
                    <th style="font-weight: bold; text-align: left">LIABILITIES</th>
                    @foreach($labels as $dateLabel)
                        <th style="font-weight: bold; text-align: right; min-width: 170px">
                            {{ $dateLabel }}
                        </th>
                    @endforeach
                </tr>
                </thead>

                <tbody>
                {{-- Current Liabilities --}}
                <tr>
                    <td colspan="{{ count($labels) + 1 }}" style="font-weight: bold; border-bottom: 2px solid black; width: 100%">Current Liabilities</td>
                </tr>
                @foreach($currentLiabilities as $account)
                    <tr>
                        <td style="padding-left: 16px">{{$account['name']}}</td>
                        <td style="text-align: right">
                            {{
                                $account['balance'] >= 0
                                    ? Number::currency($account['balance'], in: 'PHP', locale: 'en_PH')
                                    : '(' . Number::currency(abs($account['balance']), in: 'PHP', locale: 'en_PH') . ')'
                            }}
                        </td>
                        @if(isset($account['previousBalances']))
                            @foreach($account['previousBalances'] as $previousBalance)
                                <td style="text-align: right">
                                    {{
                                        $previousBalance >= 0
                                            ? Number::currency($previousBalance, in: 'PHP', locale: 'en_PH')
                                            : '(' . Number::currency(abs($previousBalance), in: 'PHP', locale: 'en_PH') . ')'
                                    }}
                                </td>
                            @endforeach
                        @endif
                    </tr>
                @endforeach
                <tr>
                    <td style="font-weight: bold; border-top: 2px solid black">Total Current Liabilities</td>
                    @foreach($totalCurrentLiabilities as $currentLiability)
                        <td style="text-align: right; font-weight: bold; border-top: 2px solid black">
                            {{
                                $currentLiability >= 0
                                    ? Number::currency($currentLiability, in: 'PHP', locale: 'en_PH')
                                    : '(' . Number::currency(abs($currentLiability), in: 'PHP', locale: 'en_PH') . ')'
                            }}
                        </td>
                    @endforeach
                </tr>
                <tr><td style="padding: 8px 0"></td></tr>

                {{-- Non-current Liabilities --}}
                <tr>
                    <td colspan="{{count($labels) + 1}}" style="font-weight: bold; border-bottom: 2px solid black;">Non-current Liabilities</td>
                </tr>
                @foreach($nonCurrentLiabilities as $account)
                    <tr>
                        <td style="padding-left: 16px">{{$account['name']}}</td>
                        <td style="text-align: right">
                            {{
                                $account['balance'] >= 0
                                    ? Number::currency($account['balance'], in: 'PHP', locale: 'en_PH')
                                    : '(' . Number::currency(abs($account['balance']), in: 'PHP', locale: 'en_PH') . ')'
                            }}
                        </td>
                        @if(isset($account['previousBalances']))
                            @foreach($account['previousBalances'] as $previousBalance)
                                <td style="text-align: right">
                                    {{
                                        $previousBalance >= 0
                                            ? Number::currency($previousBalance, in: 'PHP', locale: 'en_PH')
                                            : '(' . Number::currency(abs($previousBalance), in: 'PHP', locale: 'en_PH') . ')'
                                    }}
                                </td>
                            @endforeach
                        @endif
                    </tr>
                @endforeach
                <tr>
                    <td style="font-weight: bold; border-top: 2px solid black">Total Non-current Liabilities</td>
                    @foreach($totalNonCurrentLiabilities as $nonCurrentLiability)
                        <td style="text-align: right; font-weight: bold; border-top: 2px solid black">
                            {{
                                $nonCurrentLiability >= 0
                                    ? Number::currency($nonCurrentLiability, in: 'PHP', locale: 'en_PH')
                                    : '(' . Number::currency(abs($nonCurrentLiability), in: 'PHP', locale: 'en_PH') . ')'
                            }}
                        </td>
                    @endforeach
                </tr>
                </tbody>

                <tfoot>
                <tr>
                    <td style="font-weight: bold; border-top: 2px solid black; border-bottom: 2px solid black">Total Liabilities</td>
                    @foreach($totalLiabilities as $liability)
                        <td style="text-align: right; font-weight: bold; border-top: 2px solid black; border-bottom: 2px solid black">
                            {{
                                $liability >= 0
                                    ? Number::currency($liability, in: 'PHP', locale: 'en_PH')
                                    : '(' . Number::currency(abs($liability), in: 'PHP', locale: 'en_PH') . ')'
                            }}
                        </td>
                    @endforeach
                </tr>
                </tfoot>
            </table>

            {{-- Equity Table --}}
            <table style="margin-bottom: 25px">
                <thead>
                <tr>
                    <th style="font-weight: bold; text-align: left">EQUITIES</th>
                    @foreach($labels as $dateLabel)
                        <th style="font-weight: bold; text-align: right; min-width: 170px">
                            {{ $dateLabel }}
                        </th>
                    @endforeach
                </tr>
                </thead>

                <tbody>
                @foreach($equityAccounts as $account)
                    <tr>
                        <td style="padding-left: 16px">{{$account['name']}}</td>
                        <td style="text-align: right">
                            {{
                                $account['balance'] >= 0
                                    ? Number::currency($account['balance'], in: 'PHP', locale: 'en_PH')
                                    : '(' . Number::currency(abs($account['balance']), in: 'PHP', locale: 'en_PH') . ')'
                            }}
                        </td>
                        @if(isset($account['previousBalances']))
                            @foreach($account['previousBalances'] as $previousBalance)
                                <td style="text-align: right">
                                    {{
                                        $previousBalance >= 0
                                            ? Number::currency($previousBalance, in: 'PHP', locale: 'en_PH')
                                            : '(' . Number::currency(abs($previousBalance), in: 'PHP', locale: 'en_PH') . ')'
                                    }}
                                </td>
                            @endforeach
                        @endif
                    </tr>
                @endforeach
                <tr>
                    <td style="font-weight: bold; border-top: 2px solid black">Total Equities</td>
                    @foreach($totalEquities as $equity)
                        <td style="text-align: right; font-weight: bold; border-top: 2px solid black">
                            {{
                                $equity >= 0
                                    ? Number::currency($equity, in: 'PHP', locale: 'en_PH')
                                    : '(' . Number::currency(abs($equity), in: 'PHP', locale: 'en_PH') . ')'
                            }}
                        </td>
                    @endforeach
                </tr>
                </tbody>

                <tfoot>
                <tr>
                    <td style="font-weight: bold; border-top: 2px solid black; border-bottom: 2px solid black">Total Liabilities + Equity</td>
                    @foreach($totalLiabilitiesEquities as $liabilityEquity)
                        <td style="text-align: right; font-weight: bold; border-top: 2px solid black; border-bottom: 2px solid black">
                            {{
                                $liabilityEquity >= 0
                                    ? Number::currency($liabilityEquity, in: 'PHP', locale: 'en_PH')
                                    : '(' . Number::currency(abs($liabilityEquity), in: 'PHP', locale: 'en_PH') . ')'
                            }}
                        </td>
                    @endforeach
                </tr>
                </tfoot>
            </table>
        </main>

        <script type="text/php">
            if (isset($pdf)) {
                // Page text and styling
                $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
                $size = 8;
                $font = $fontMetrics->getFont("DejaVu Sans");

                // Manual calculation on where to put the page text
                $textWidth = $fontMetrics->get_text_width($text, $font, $size);
                $x = $pdf->get_width() - 116;
                $y = $pdf->get_height() - 52;

                // Render page text
                $pdf->page_text($x, $y, $text, $font, $size);
            }
        </script>
    </body>
</html>
