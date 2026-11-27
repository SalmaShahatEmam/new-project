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
                'name' => 'count_projects',
                'description' => 'الاستعلام عن المشاريع في المنظومة، حصرها، معرفة عددها، قائمة المشاريع الموجودة وأسماؤها وحالاتها وتواريخها وتفاصيلها العامة (ما هي المشاريع الموجودة في النظام، كم عدد المشاريع، قائمة المشاريع، المشاريع الحالية وتفاصيلها، أسماء المشاريع وحالاتها).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'status' => [
                            'type' => 'STRING',
                            'description' => 'حالة المشاريع المطلوب الاستعلام عنها مثل in_progress, pending, completed (اختياري)',
                        ],
                    ],
                ],
            ],
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
            [
                'name' => 'get_employees_and_staff_details',
                'description' => 'الاستعلام عن الموظفين والمهندسين في المنظومة، وظائفهم، أدوارهم، معلومات الاتصال (الهاتف والإيميل)، المشاريع والمواقع الميدانية المسندة إليهم، وحالات نشاطهم (من هم المهندسون، مهندسو مشروع كذا، وظيفة فلان، قائمة الموظفين، مهندس إنشائي أو معماري).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'employee_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم الموظف أو المهندس أو جزء منه',
                        ],
                        'role_or_type' => [
                            'type' => 'STRING',
                            'description' => 'الدور أو التخصص الهندسي مثل Structural (إنشائي), Architectural (معماري), Electrical (كهرباء), Mechanical (ميكانيك), admin, engineer',
                        ],
                        'project_id' => [
                            'type' => 'INTEGER',
                            'description' => 'معرف المشروع لحصر المهندسين القائمين عليه',
                        ],
                        'project_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم المشروع لحصر المهندسين القائمين عليه',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_sites_and_locations_summary',
                'description' => 'الاستعلام عن المواقع الميدانية (Sites) والمواقع الجغرافية (Locations)، مدد العقود، المشاريع التابعة لها، المهندسين المعينين بكل موقع، وإحصائيات التقارير والمشاكل الميدانية لكل موقع (مواقع المشروع، كم موقع في برج رتال، تفاصيل موقع كذا).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'site_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم الموقع الميداني أو جزء منه',
                        ],
                        'site_id' => [
                            'type' => 'INTEGER',
                            'description' => 'المعرف الرقمي للموقع الميداني',
                        ],
                        'project_id' => [
                            'type' => 'INTEGER',
                            'description' => 'معرف المشروع لحصر المواقع التابعة له',
                        ],
                        'project_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم المشروع لحصر المواقع التابعة له',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_combined_and_aggregated_reports',
                'description' => 'الاستعلام عن التقارير المجمعة (Combined Reports) والتقارير الميدانية المجمعة لفترة زمنية محددة أو موقع معين، وتلخيص شامل لاستهلاك المواد، تشغيل المعدات، حضور العمالة، والمشاكل الميدانية خلال فترة محددة.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'site_id' => [
                            'type' => 'INTEGER',
                            'description' => 'معرف الموقع الميداني',
                        ],
                        'site_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم الموقع الميداني',
                        ],
                        'project_id' => [
                            'type' => 'INTEGER',
                            'description' => 'معرف المشروع',
                        ],
                        'project_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم المشروع',
                        ],
                        'from_date' => [
                            'type' => 'STRING',
                            'description' => 'تاريخ البداية بصيغة YYYY-MM-DD',
                        ],
                        'to_date' => [
                            'type' => 'STRING',
                            'description' => 'تاريخ النهاية بصيغة YYYY-MM-DD',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_suppliers_and_procurement_details',
                'description' => 'الاستعلام الشامل عن الموردين ومقاولي الباطن، بيانات الاتصال، المواد التي يوردونها، أحدث أسعارهم المسجلة، التقارير الميدانية المرتبطة بهم، وحالات نشاطهم (موردو الأسمنت، قائمة الموردين، مقاول الباطن، بيانات المورد).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'supplier_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم المورد أو مقاول الباطن أو جزء منه',
                        ],
                        'material_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم المادة أو الخامة لحصر الموردين الذين يوفرونها',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_resource_prices_and_market_rates',
                'description' => 'الاستعلام عن سجل وتاريخ أسعار المواد والخامات (Resource Prices)، والأسعار الحالية قبل وبعد الضريبة، العملة، التواريخ، والمقارنة بين الموردين أو المواقع المختلفة لمادة معينة (أسعار المواد، سعر طن الحديد، سعر كيس الأسمنت، سجل الأسعار، أرخص مورد).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'material_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم المادة أو الخامة أو كودها (حديد، أسمنت، خرسانة، إلخ)',
                        ],
                        'supplier_name' => [
                            'type' => 'STRING',
                            'description' => 'اسم المورد المحدد للاستعلام عن أسعاره',
                        ],
                    ],
                ],
            ],
        ];
    }
}
