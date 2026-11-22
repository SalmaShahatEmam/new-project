<?php

namespace App\Services\ExecutiveAssistant;

class ExecutiveAssistantToolRegistry
{
    /**
     * Get all Gemini function declarations.
     *
     * @return array
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'get_project_executive_status',
                'description' => 'الحصول على الموقف التنفيذي واليومي الشامل لمشروع ومواقعه عبر ركائز التنفيذ الأربع (المواد، المعدات، العمالة، المشاكل والمعوقات الميدانية) والتكاليف والتقارير الميدانية.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'project_id' => [
                            'type' => 'INTEGER',
                            'description' => 'المعرف الرقمي للمشروع (Project ID) إذا كان معروفاً',
                        ],
                        'project_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم المشروع أو كلمة بحث دالة على اسم المشروع',
                        ],
                        'site_id' => [
                            'type' => 'INTEGER',
                            'description' => 'المعرف الرقمي للموقع الميداني (Site ID) إذا كان الاستفسار عن موقع محدد',
                        ],
                        'date' => [
                            'type' => 'STRING',
                            'description' => 'تاريخ التقرير اليومي المطلوب بالصيغة YYYY-MM-DD (اختياري)',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'check_material_stock_and_quantities',
                'description' => 'الاستعلام عن كميات وأرصدة المواد والخامات (الحديد، الأسمنت، الخرسانة، إلخ) ومطابقتها مع المخصصات والتوريدات الميدانية المعتمدة وأحدث أسعارها.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'material_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم المادة أو الخامة أو كود المادة (مثل حديد، أسمنت، بلوك)',
                        ],
                        'project_id' => [
                            'type' => 'INTEGER',
                            'description' => 'معرف المشروع لحصر كميات المواد المخصصة له',
                        ],
                        'project_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم المشروع لحصر كميات المواد المخصصة له',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'query_subcontractor_and_milestone_delays',
                'description' => 'الاستعلام عن أداء مقاولي الباطن والموردين، وحالات التأخير عن الجداول الزمنية ومواعيد تسليم المشاريع، والمشاكل الميدانية المفتوحة.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'project_id' => [
                            'type' => 'INTEGER',
                            'description' => 'معرف المشروع للاستعلام عن تاريخ التسليم والتعثر والتأخير',
                        ],
                        'project_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم المشروع أو جزء من اسمه',
                        ],
                        'supplier_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم مقاول الباطن أو المورد للاستعلام عن أدائه والمشاكل المنسوبة إليه',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_orders_and_approvals_status',
                'description' => 'الاستعلام عن أوامر الشراء والطلبيات الميدانية، ومواقف الاعتماد والتعميد، والمواد المعلقة أو المرفوضة أو المعادة للمراجعة ومعوقات التوريد.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'project_id' => [
                            'type' => 'INTEGER',
                            'description' => 'معرف المشروع لحصر أوامر الشراء الخاصة به',
                        ],
                        'status' => [
                            'type' => 'STRING',
                            'description' => 'حالة الطلب أو بنوده: unapproved (معلق), approved (معتمد), rejected (مرفوض), returned_for_revision (معاد للمراجعة)',
                        ],
                        'order_number' => [
                            'type' => 'STRING',
                            'description' => 'رقم أمر الشراء المحدد',
                        ],
                    ],
                ],
            ],
        ];
    }
}
