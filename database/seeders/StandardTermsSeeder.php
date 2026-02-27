<?php

namespace Database\Seeders;

use App\Models\StandardTerm;
use Illuminate\Database\Seeder;

class StandardTermsSeeder extends Seeder
{
    public function run(): void
    {
        $terms = [
            [
                'code'       => 'PO_STD_MATERIAL',
                'name'       => 'PO - Standard Material Terms',
                'module'     => 'purchase',
                'sub_module' => 'po',
                'content'    => <<<TEXT
1. Prices are firm and not subject to escalation unless otherwise agreed in writing.
2. Material must strictly conform to the specification, grade and sizes mentioned in this Purchase Order.
3. Any deviation or change must be approved in writing by the purchaser before supply.
4. Delivery shall be made on or before the expected delivery date. Any delay is subject to rejection of material and/or liquidated damages as per purchaser policy.
5. All invoices must clearly mention PO number, item description, quantity and applicable taxes.
6. Payment will be made as per the agreed payment terms, subject to receipt and acceptance of material and all required documents.
7. Goods are subject to inspection at our site. Rejected material shall be taken back by the supplier at their own cost.
8. Risk and ownership of goods shall pass to the purchaser only after receipt and acceptance at the designated delivery location.
9. Any disputes arising out of this Purchase Order shall be subject to the jurisdiction of the purchaser's registered office location, unless otherwise specified.
10. This Purchase Order, along with any referenced documents, constitutes the entire agreement between purchaser and supplier for the described goods/services.
TEXT,
                'is_default' => true,
                'is_active'  => true,
                'version'    => 1,
                'sort_order' => 1,
            ],
            [
                'code'       => 'PO_SUBCONTRACT',
                'name'       => 'PO - Subcontract / Service Terms',
                'module'     => 'purchase',
                'sub_module' => 'po',
                'content'    => <<<TEXT
1. Contractor shall comply with all site safety rules and statutory regulations.
2. Work shall be executed strictly as per drawings, specifications and instructions of the engineer in charge.
3. All tools, tackles, manpower, consumables and supervision shall be arranged by the contractor unless otherwise specified.
4. Measurement and certification of work done by the purchaser's representative shall be final and binding.
5. Payments shall be released as per agreed milestones / RA bills after necessary deductions and approvals.
6. Any damage to purchaser property caused by contractor shall be made good at contractor's cost.
7. Contractor shall ensure compliance with all labour laws, insurance and statutory obligations for their personnel.
8. Any disputes arising out of this contract shall be subject to the jurisdiction of the purchaser's registered office location, unless otherwise specified.
TEXT,
                'is_default' => false,
                'is_active'  => true,
                'version'    => 1,
                'sort_order' => 2,
            ],
        ];

        foreach ($terms as $data) {
            StandardTerm::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
