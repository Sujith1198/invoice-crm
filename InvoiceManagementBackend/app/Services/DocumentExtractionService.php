<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Item;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class DocumentExtractionService
{
    protected Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Extract structured invoice data from a PDF file
     */
    public function extractFromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found at path: {$filePath}");
        }

        $rawText = '';
        $pagesText = [];
        $pageCount = 0;

        try {
            $pdf = $this->parser->parseFile($filePath);
            $pages = $pdf->getPages();
            $pageCount = count($pages);

            foreach ($pages as $index => $page) {
                $pText = $page->getText();
                $pagesText[] = $pText;
                $rawText .= "\n--- Page " . ($index + 1) . " ---\n" . $pText;
            }
        } catch (\Throwable $e) {
            Log::warning("PdfParser error: " . $e->getMessage());
            $rawText = '';
        }

        $cleanedText = trim($rawText);
        $isScanned = strlen($cleanedText) < 40;

        // OCR fallback if scanned and tesseract is available
        $ocrAttempted = false;
        $ocrAvailable = $this->isOcrAvailable();

        if ($isScanned && $ocrAvailable) {
            $ocrText = $this->runOcr($filePath);
            if (!empty($ocrText)) {
                $cleanedText = $ocrText;
                $isScanned = false;
                $ocrAttempted = true;
            }
        }

        // Extract sections
        $customerData = $this->extractCustomer($cleanedText);
        $invoiceDetails = $this->extractInvoiceDetails($cleanedText);
        $items = $this->extractItems($cleanedText);
        $totals = $this->extractTotals($cleanedText, $items);

        // Perform matching against existing database
        $customerMatch = $this->matchCustomer($customerData);
        $matchedItems = $this->matchItems($items);

        // Validate and evaluate confidence
        $validation = $this->validateExtraction($customerData, $invoiceDetails, $matchedItems, $totals, $customerMatch);

        return [
            'raw_text_preview' => mb_substr($cleanedText, 0, 1000),
            'page_count' => $pageCount,
            'is_scanned' => $isScanned,
            'ocr_attempted' => $ocrAttempted,
            'ocr_available' => $ocrAvailable,
            'customer' => $customerData,
            'customer_match' => $customerMatch,
            'invoice' => $invoiceDetails,
            'items' => $matchedItems,
            'totals' => $totals,
            'validation' => $validation,
        ];
    }

    /**
     * Extract customer information
     */
    protected function extractCustomer(string $text): array
    {
        $customer = [
            'first_name' => '',
            'last_name' => '',
            'company_name' => '',
            'email' => '',
            'phone' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'country' => 'India',
            'payment_method' => 'Monthly Pay',
        ];

        // 1. Locate BILLED TO / CUSTOMER section (prioritize exact billing keywords over loose words)
        $billedSection = '';
        if (preg_match('/\b(?:BILLED\s+TO|BILL\s+TO|INVOICE\s+TO|BUYER(?:\s+DETAILS)?|SOLD\s+TO|CUSTOMER\s+DETAILS)\b[:\s]*(.*?)(?=(?:\bINVOICE\s+DETAILS\b|\bINVOICE\s+DATE\b|\bS\.NO\b|\bITEM\b|\bDESCRIPTION\b|\bPAYMENT\s+HISTORY\b|$))/is', $text, $m)) {
            $billedSection = trim($m[1]);
        } elseif (preg_match('/(?:^|\n)\s*(?:CUSTOMER|CLIENT)\s*[:\s]+(.*?)(?=(?:\bINVOICE\s+DETAILS\b|\bINVOICE\s+DATE\b|\bS\.NO\b|\bITEM\b|\bDESCRIPTION\b|\bPAYMENT\s+HISTORY\b|$))/is', $text, $m)) {
            $billedSection = trim($m[1]);
        }

        // 2. Extract Email (prioritize within billed section)
        if (!empty($billedSection) && preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $billedSection, $m)) {
            $customer['email'] = strtolower($m[0]);
        } elseif (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $allM)) {
            foreach ($allM[0] as $em) {
                if (!str_contains(strtolower($em), 'orangeinvoice.com') && !str_contains(strtolower($em), 'sujithp249')) {
                    $customer['email'] = strtolower($em);
                    break;
                }
            }
        }

        // 3. Extract Phone (prioritize within billed section)
        if (!empty($billedSection) && preg_match('/(?:Phone|Tel|Mobile|Contact|Ph)?[:\s]*(\+?\d{1,4}[-.\s]?(?:\(?\d{2,5}\)?[-.\s]?)?\d{3,5}[-.\s]?\d{3,5})/i', $billedSection, $m)) {
            $customer['phone'] = trim($m[1]);
        } elseif (preg_match_all('/(?:Phone|Tel|Mobile|Contact|Ph)[:\s]*(\+?\d{1,4}[-.\s]?(?:\(?\d{2,5}\)?[-.\s]?)?\d{3,5}[-.\s]?\d{3,5})/i', $text, $allM)) {
            $customer['phone'] = trim(end($allM[1]));
        }

        // 4. Extract Payment Method from Billed Section if present
        if (!empty($billedSection) && preg_match('/Payment Method:\s*([^\n\r]+)/i', $billedSection, $m)) {
            $customer['payment_method'] = trim($m[1]);
        }

        // 5. Parse Name, Company, and Address from Billed Section lines
        if (!empty($billedSection)) {
            $rawLines = explode("\n", $billedSection);
            $cleanLines = [];
            foreach ($rawLines as $l) {
                $trimmed = trim($l);
                if (empty($trimmed)) continue;
                if (preg_match('/^(Phone|Email|Payment Method|GSTIN|PAN|CIN):/i', $trimmed)) continue;
                $cleanLines[] = $trimmed;
            }

            if (!empty($cleanLines)) {
                $line0 = $cleanLines[0];
                if (preg_match('/\b(Ltd|Limited|Pvt|LLC|Inc|Corp|Corporation|Enterprises|Technologies|Solutions|Trading|Works|Services)\b/i', $line0)) {
                    $customer['company_name'] = $line0;
                    if (isset($cleanLines[1]) && !preg_match('/(Street|Road|Plot|Floor|Avenue|Sector|Phase|Harbor|Suite|\d+)/i', $cleanLines[1])) {
                        $this->splitName($cleanLines[1], $customer);
                        $addrStart = 2;
                    } else {
                        $addrStart = 1;
                    }
                } else {
                    $this->splitName($line0, $customer);
                    if (isset($cleanLines[1]) && preg_match('/\b(Ltd|Limited|Pvt|LLC|Inc|Corp|Corporation|Enterprises|Technologies|Solutions|Trading|Works|Services|Logistics)\b/i', $cleanLines[1])) {
                        $customer['company_name'] = $cleanLines[1];
                        $addrStart = 2;
                    } else {
                        $addrStart = 1;
                    }
                }

                // Address lines
                $addrLines = [];
                for ($i = $addrStart; $i < count($cleanLines); $i++) {
                    $addrLines[] = $cleanLines[$i];
                }
                if (!empty($addrLines)) {
                    $customer['address_line1'] = $addrLines[0];
                    if (isset($addrLines[1])) {
                        $customer['address_line2'] = $addrLines[1];
                    }
                }
            }
        }

        // 6. Extract City
        $commonCities = ['Bengaluru', 'Bangalore', 'Mumbai', 'Kochi', 'Faridabad', 'Vadodara', 'Delhi', 'New Delhi', 'Chennai', 'Hyderabad', 'Kolkata', 'Pune', 'Ahmedabad', 'Jaipur', 'Surat'];
        $searchTarget = !empty($billedSection) ? $billedSection : $text;
        foreach ($commonCities as $city) {
            if (preg_match('/\b' . preg_quote($city, '/') . '\b/i', $searchTarget)) {
                $customer['city'] = $city;
                break;
            }
        }

        return $customer;
    }

    protected function splitName(string $fullName, array &$customer): void
    {
        $parts = preg_split('/\s+/', trim($fullName));
        if (count($parts) === 1) {
            $customer['first_name'] = $parts[0];
            $customer['last_name'] = '';
        } elseif (count($parts) >= 2) {
            $customer['first_name'] = $parts[0];
            $customer['last_name'] = implode(' ', array_slice($parts, 1));
        }
    }

    /**
     * Extract invoice level attributes
     */
    protected function extractInvoiceDetails(string $text): array
    {
        $details = [
            'document_number' => '',
            'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+15 days')),
            'payment_terms' => 'Net 15 days',
            'payment_method' => 'Monthly Pay',
            'currency' => 'INR',
            'notes' => '',
        ];

        // 1. Invoice / Document Number
        if (preg_match('/\b(20\d{6}\d{4})\b/', $text, $m)) { // specific YYYYMMDD0001 sequence
            $details['document_number'] = $m[1];
        } elseif (preg_match('/(?:TAX\s+INVOICE|INVOICE\s*(?:NO|NUMBER|#)?|INV\s*#?|REF\s*#?)\s*[:#\s]*([A-Z0-9\-_]{4,24})/i', $text, $m)) {
            $details['document_number'] = trim($m[1]);
        }

        // 2. Invoice Date
        if (preg_match('/(?:Invoice Date|Date of Issue|Dated|Date)[:\s\t]*([0-9]{1,2}[-\/\s]+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|[0-9]{1,2})[,\-\/\s]+[0-9]{2,4})/i', $text, $m)) {
            $parsed = $this->parseDate($m[1]);
            if ($parsed) $details['invoice_date'] = $parsed;
        }

        // 3. Due Date
        if (preg_match('/(?:Due Date|Payment Due|Due)[:\s\t]*([0-9]{1,2}[-\/\s]+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec|[0-9]{1,2})[,\-\/\s]+[0-9]{2,4})/i', $text, $m)) {
            $parsed = $this->parseDate($m[1]);
            if ($parsed) $details['due_date'] = $parsed;
        }

        // 4. Payment Terms
        if (preg_match('/(?:Payment Terms|Terms)[:\s]*([^\n\r,]+)/i', $text, $m)) {
            $details['payment_terms'] = trim($m[1]);
        }

        // 5. Payment Method
        if (preg_match('/(?:Payment Method)[:\s]*([^\n\r,]+)/i', $text, $m)) {
            $method = trim($m[1]);
            if (preg_match('/Daily/i', $method)) $details['payment_method'] = 'Daily Pay';
            elseif (preg_match('/15\s*Days/i', $method)) $details['payment_method'] = '15 Days Pay';
            elseif (preg_match('/Monthly/i', $method)) $details['payment_method'] = 'Monthly Pay';
            else $details['payment_method'] = $method;
        }

        // 6. Currency
        if (str_contains($text, '₹') || preg_match('/\bINR\b/', $text)) {
            $details['currency'] = 'INR';
        } elseif (str_contains($text, '$') || preg_match('/\bUSD\b/', $text)) {
            $details['currency'] = 'USD';
        } elseif (str_contains($text, '€') || preg_match('/\bEUR\b/', $text)) {
            $details['currency'] = 'EUR';
        }

        // 7. Notes
        if (preg_match('/(?:NOTES\s*(?:\/|\&)\s*REMARKS|NOTES|REMARKS)[:\s]+(.*?)(?=(?:TERMS|\n\s*\n|Subtotal|Grand Total|$))/is', $text, $m)) {
            $details['notes'] = trim($m[1]);
        }

        return $details;
    }

    protected function parseDate(string $rawDate): ?string
    {
        $rawDate = trim(str_replace(',', ' ', $rawDate));
        try {
            return Carbon::parse($rawDate)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extract line items
     */
    protected function extractItems(string $text): array
    {
        $items = [];
        $lines = explode("\n", $text);
        $inItemsTable = false;
        $pendingLines = [];

        foreach ($lines as $line) {
            $trimLine = trim($line);
            if (empty($trimLine)) continue;

            if (preg_match('/\b(?:S\.?NO|ITEM|DESCRIPTION)\b/i', $trimLine) ||
                preg_match('/\b(?:PRICE|RATE)\b.*\b(?:QTY|TOTAL)/i', $trimLine)) {
                $inItemsTable = true;
                $pendingLines = [];
                continue;
            }

            if ($inItemsTable) {
                // Ignore table sub-headers
                if (preg_match('/^(?:PROCEED\s*\/|DESCRIPTION|PRICE\s+QTY\s+TOTAL)$/i', $trimLine)) {
                    continue;
                }

                // Check if we hit summary / footer / remarks section
                if (preg_match('/^(?:Subtotal|Total\s+GST|Discount|Grand\s+Total|NOTES|TERMS|PAYMENT\s+HISTORY|Amount\s+Paid|Balance\s+Due)/i', $trimLine)) {
                    break;
                }

                // Check if this line contains price, qty, and total
                // E.g. "45,000.00 2 90,000.00" or "Hydraulic Valve 12,500.00 2 25,000.00"
                if (preg_match('/^(.*?)(?:^|\s+)([\d,]+\.\d{2})\s+(\d+(?:\.\d+)?)\s+([\d,]+\.\d{2})$/', $trimLine, $m)) {
                    $inlineDesc = trim($m[1]);
                    $rate = (float) str_replace(',', '', $m[2]);
                    $qty = (float) $m[3];
                    $total = (float) str_replace(',', '', $m[4]);

                    // Determine Item Description
                    $desc = $inlineDesc;
                    if (empty($desc) && !empty($pendingLines)) {
                        $descCandidates = [];
                        foreach ($pendingLines as $pl) {
                            if (!preg_match('/^(?:GST:|S\.NO|PRICE|QTY|TOTAL|\d+$)/i', $pl)) {
                                $cleaned = preg_replace('/^\d+\s+/', '', $pl);
                                if (!empty($cleaned)) {
                                    $descCandidates[] = $cleaned;
                                }
                            }
                        }
                        if (!empty($descCandidates)) {
                            // First line or first 2 lines if short
                            if (count($descCandidates) > 1 && strlen($descCandidates[0]) < 35 && strlen($descCandidates[1]) < 20) {
                                $desc = $descCandidates[0] . ' ' . $descCandidates[1];
                            } else {
                                $desc = $descCandidates[0];
                            }
                        }
                    }

                    // Check for GST percentage in pending lines
                    $gstPct = 18.00;
                    foreach ($pendingLines as $pl) {
                        if (preg_match('/GST:\s*([\d.]+)%/i', $pl, $gm)) {
                            $gstPct = (float) $gm[1];
                        }
                    }

                    if (!empty($desc)) {
                        $items[] = [
                            'item_name' => $desc,
                            'description' => $desc,
                            'quantity' => $qty > 0 ? $qty : 1,
                            'unit_price' => $rate,
                            'gst_percentage' => $gstPct,
                            'amount' => $total,
                            'matched_item_id' => null,
                            'matched_item_name' => null,
                            'is_matched' => false,
                        ];
                    }
                    $pendingLines = [];
                    continue;
                }

                $pendingLines[] = $trimLine;
            }
        }

        // Fallback: If no structured items found, search for any prominent item lines
        if (empty($items)) {
            if (preg_match_all('/([A-Za-z0-9\s\-_()]{4,50})\s+(?:GST:\s*[\d.]+%?\s*)?([\d,]+\.\d{2})/i', $text, $allMatches, PREG_SET_ORDER)) {
                foreach ($allMatches as $match) {
                    $name = trim($match[1]);
                    $val = (float) str_replace(',', '', $match[2]);
                    if (!preg_match('/(Subtotal|Total|Discount|Grand Total|Balance Due|Amount Paid|Phone)/i', $name) && $val > 0) {
                        $items[] = [
                            'item_name' => $name,
                            'description' => $name,
                            'quantity' => 1,
                            'unit_price' => $val,
                            'gst_percentage' => 18.00,
                            'amount' => $val,
                            'matched_item_id' => null,
                            'matched_item_name' => null,
                            'is_matched' => false,
                        ];
                        if (count($items) >= 5) break;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Extract subtotal, gst, discount, additional charges and grand total
     */
    protected function extractTotals(string $text, array $items): array
    {
        $totals = [
            'subtotal' => 0.0,
            'total_gst' => 0.0,
            'discount' => 0.0,
            'additional_charges' => 0.0,
            'grand_total' => 0.0,
        ];

        // 1. Subtotal
        if (preg_match('/(?:Subtotal|Sub Total)[:\s\t]*([₹$€£\s]*)([\d,]+\.?\d*)/i', $text, $m)) {
            $totals['subtotal'] = (float) str_replace(',', '', $m[2]);
        }

        // 2. Total GST / Tax - prioritize "Total GST" or "Total Tax" over standalone "GST"
        if (preg_match('/(?:Total\s+GST|Total\s+Tax)[:\s\t]*([₹$€£\s]*)([\d,]+\.?\d*)/i', $text, $m)) {
            $totals['total_gst'] = (float) str_replace(',', '', $m[2]);
        } elseif (preg_match('/(?:GST|Tax)[:\s\t]*([₹$€£\s]*)([\d,]+\.?\d*)/i', $text, $m)) {
            $totals['total_gst'] = (float) str_replace(',', '', $m[2]);
        }

        // 3. Discount
        if (preg_match('/(?:Discount\s*(?:\([+-]\))?)[:\s\t]*([₹$€£\s\-]*)([\d,]+\.?\d*)/i', $text, $m)) {
            $totals['discount'] = (float) str_replace(',', '', $m[2]);
        }

        // 4. Additional Charges / Shipping
        if (preg_match('/(?:Additional\s*Charges|Shipping|Freight)\s*(?:\([+-]\))?[:\s\t]*([₹$€£\s\+]*)([\d,]+\.?\d*)/i', $text, $m)) {
            $totals['additional_charges'] = (float) str_replace(',', '', $m[2]);
        }

        // 5. Grand Total - require Grand Total or Total Amount specifically so it never matches the "TOTAL" column header
        if (preg_match('/(?:Grand\s+Total|Total\s+Amount|Invoice\s+Total|Final\s+Total|Net\s+Total)[:\s\t]*([₹$€£\s]*)([\d,]+\.?\d*)/i', $text, $m)) {
            $totals['grand_total'] = (float) str_replace(',', '', $m[2]);
        } elseif (preg_match('/(?:^|\n)\s*Total[:\s\t]+([₹$€£\s]*)([\d,]+\.?\d*)/i', $text, $m)) {
            $totals['grand_total'] = (float) str_replace(',', '', $m[2]);
        }

        // Recalculate or fill fallback if zero
        if ($totals['subtotal'] <= 0 && !empty($items)) {
            $sum = 0;
            foreach ($items as $it) {
                $sum += ($it['unit_price'] * $it['quantity']);
            }
            $totals['subtotal'] = round($sum, 2);
        }

        if ($totals['grand_total'] <= 0 && $totals['subtotal'] > 0) {
            $totals['grand_total'] = round($totals['subtotal'] + $totals['total_gst'] - $totals['discount'] + $totals['additional_charges'], 2);
        }

        return $totals;
    }

    /**
     * Match extracted customer against database
     */
    protected function matchCustomer(array $customer): ?array
    {
        $match = null;
        $matchType = '';
        $confidence = 0;

        // 1. Match by Email
        if (!empty($customer['email'])) {
            $found = Customer::with('phones')->where('email', $customer['email'])->first();
            if ($found) {
                $match = $found;
                $matchType = 'email';
                $confidence = 98;
            }
        }

        // 2. Match by Phone
        if (!$match && !empty($customer['phone'])) {
            $cleanPhone = preg_replace('/[^\d]/', '', $customer['phone']);
            if (strlen($cleanPhone) >= 7) {
                $found = Customer::with('phones')->whereHas('phones', function ($q) use ($cleanPhone) {
                    $q->where('phone_number', 'like', "%{$cleanPhone}%");
                })->first();
                if ($found) {
                    $match = $found;
                    $matchType = 'phone';
                    $confidence = 92;
                }
            }
        }

        // 3. Match by Company Name
        if (!$match && !empty($customer['company_name'])) {
            $cleanComp = trim($customer['company_name']);
            if (strlen($cleanComp) > 3) {
                $found = Customer::with('phones')->where('company_name', 'like', "%{$cleanComp}%")->first();
                if ($found) {
                    $match = $found;
                    $matchType = 'company';
                    $confidence = 88;
                }
            }
        }

        // 4. Match by First & Last Name
        if (!$match && !empty($customer['first_name'])) {
            $q = Customer::with('phones')->where('first_name', 'like', "%{$customer['first_name']}%");
            if (!empty($customer['last_name'])) {
                $q->where('last_name', 'like', "%{$customer['last_name']}%");
            }
            $found = $q->first();
            if ($found) {
                $match = $found;
                $matchType = 'name';
                $confidence = 80;
            }
        }

        if ($match) {
            return [
                'id' => $match->id,
                'name' => $match->full_name ?? ($match->first_name . ' ' . $match->last_name),
                'first_name' => $match->first_name,
                'last_name' => $match->last_name,
                'company_name' => $match->company_name,
                'email' => $match->email,
                'phone' => $match->primary_phone ?? ($match->phones->first()?->phone_number ?? ''),
                'address' => trim($match->address_line1 . ' ' . ($match->address_line2 ?? '')),
                'city' => $match->city,
                'country' => $match->country,
                'payment_method' => $match->payment_method,
                'match_type' => $matchType,
                'confidence' => $confidence,
            ];
        }

        return null;
    }

    /**
     * Match extracted items against database items
     */
    protected function matchItems(array $items): array
    {
        $allDbItems = Item::where('status', 'active')->get();

        foreach ($items as &$item) {
            $itemName = trim($item['item_name']);
            $bestMatch = null;
            $bestScore = 0;

            foreach ($allDbItems as $dbItem) {
                // Exact match
                if (strcasecmp($dbItem->item_name, $itemName) === 0) {
                    $bestMatch = $dbItem;
                    $bestScore = 100;
                    break;
                }

                // Substring or keyword match
                if (stripos($dbItem->item_name, $itemName) !== false || stripos($itemName, $dbItem->item_name) !== false) {
                    $bestMatch = $dbItem;
                    $bestScore = 85;
                }
            }

            if ($bestMatch) {
                $item['matched_item_id'] = $bestMatch->id;
                $item['matched_item_name'] = $bestMatch->item_name;
                $item['is_matched'] = true;
                $item['confidence'] = $bestScore;
                if (!isset($item['gst_percentage']) || $item['gst_percentage'] <= 0) {
                    $item['gst_percentage'] = (float) $dbItem->gst_percentage;
                }
            } else {
                $item['matched_item_id'] = null;
                $item['matched_item_name'] = null;
                $item['is_matched'] = false;
                $item['confidence'] = 0;
            }
        }

        return $items;
    }

    /**
     * Validate extraction data and identify discrepancies
     */
    protected function validateExtraction(array $customer, array $invoice, array $items, array $totals, ?array $matchedCustomer): array
    {
        $missingFields = [];
        $calculationErrors = [];

        if (empty($customer['first_name']) && empty($customer['company_name'])) {
            $missingFields[] = 'Customer Name or Company';
        }
        if (empty($invoice['invoice_date'])) {
            $missingFields[] = 'Invoice Date';
        }
        if (empty($items)) {
            $missingFields[] = 'Line Items';
        }

        // Calculation consistency check
        $calcSubtotal = 0;
        foreach ($items as $idx => $it) {
            $lineExpected = round($it['unit_price'] * $it['quantity'], 2);
            $lineActual = round($it['amount'], 2);
            if ($lineActual > 0 && abs($lineExpected - $lineActual) > 1.0) {
                $calculationErrors[] = "Item #" . ($idx + 1) . " amount (₹{$lineActual}) differs from Rate × Qty (₹{$lineExpected})";
            }
            $calcSubtotal += $lineExpected;
        }

        $grandExpected = round($totals['subtotal'] + $totals['total_gst'] - $totals['discount'] + $totals['additional_charges'], 2);
        if ($totals['grand_total'] > 0 && abs($grandExpected - $totals['grand_total']) > 2.0) {
            $calculationErrors[] = "Grand Total (₹{$totals['grand_total']}) does not match Subtotal + GST - Discount + Charges (₹{$grandExpected})";
        }

        $hasCustomerMatch = $matchedCustomer !== null;
        $unmatchedItemCount = count(array_filter($items, fn($it) => empty($it['is_matched'])));

        // Determine overall confidence
        $confidence = 'high';
        if (!empty($missingFields) || count($calculationErrors) > 1 || (!$hasCustomerMatch && count($items) === 0)) {
            $confidence = 'low';
        } elseif (!$hasCustomerMatch || $unmatchedItemCount > 0 || !empty($calculationErrors)) {
            $confidence = 'medium';
        }

        return [
            'is_valid' => empty($missingFields),
            'confidence' => $confidence,
            'missing_fields' => $missingFields,
            'calculation_errors' => $calculationErrors,
            'customer_matched' => $hasCustomerMatch,
            'unmatched_items_count' => $unmatchedItemCount,
        ];
    }

    protected function isOcrAvailable(): bool
    {
        // Check if tesseract binary or configurable path is executable
        $tesseractPath = env('TESSERACT_PATH', 'tesseract');
        $output = [];
        $returnVar = 1;
        @exec("{$tesseractPath} --version 2>&1", $output, $returnVar);
        return $returnVar === 0;
    }

    protected function runOcr(string $pdfPath): string
    {
        // Graceful OCR execution if tesseract is configured
        try {
            $tesseractPath = env('TESSERACT_PATH', 'tesseract');
            $txtOutput = tempnam(sys_get_temp_dir(), 'ocr_out');
            exec("{$tesseractPath} \"{$pdfPath}\" \"{$txtOutput}\" 2>&1");
            $resultFile = $txtOutput . '.txt';
            if (file_exists($resultFile)) {
                $content = file_get_contents($resultFile);
                @unlink($resultFile);
                @unlink($txtOutput);
                return $content ?: '';
            }
        } catch (\Throwable $e) {
            Log::warning("OCR execution failed: " . $e->getMessage());
        }
        return '';
    }
}
