<?php

namespace Modules\Egypt\Services;

/**
 * The 27 Egyptian governorates with ISO 3166-2:EG codes, names and the
 * default shipping rate (EGP) used when the store is first set up.
 */
class GovernorateList
{
    /** code => [english, arabic, default cost, eta min, eta max] */
    public const ALL = [
        'C'   => ['Cairo', 'القاهرة', 50, 1, 2],
        'GZ'  => ['Giza', 'الجيزة', 50, 1, 2],
        'ALX' => ['Alexandria', 'الإسكندرية', 60, 2, 3],
        'KB'  => ['Qalyubia', 'القليوبية', 55, 1, 3],
        'DK'  => ['Dakahlia', 'الدقهلية', 65, 2, 4],
        'SHR' => ['Sharqia', 'الشرقية', 65, 2, 4],
        'GH'  => ['Gharbia', 'الغربية', 65, 2, 4],
        'MNF' => ['Monufia', 'المنوفية', 65, 2, 4],
        'BH'  => ['Beheira', 'البحيرة', 65, 2, 4],
        'KFS' => ['Kafr El Sheikh', 'كفر الشيخ', 65, 2, 4],
        'DT'  => ['Damietta', 'دمياط', 65, 2, 4],
        'PTS' => ['Port Said', 'بورسعيد', 65, 2, 4],
        'IS'  => ['Ismailia', 'الإسماعيلية', 65, 2, 4],
        'SUZ' => ['Suez', 'السويس', 65, 2, 4],
        'FYM' => ['Faiyum', 'الفيوم', 70, 2, 4],
        'BNS' => ['Beni Suef', 'بني سويف', 70, 2, 4],
        'MN'  => ['Minya', 'المنيا', 75, 3, 5],
        'AST' => ['Asyut', 'أسيوط', 75, 3, 5],
        'SHG' => ['Sohag', 'سوهاج', 80, 3, 5],
        'KN'  => ['Qena', 'قنا', 80, 3, 5],
        'LX'  => ['Luxor', 'الأقصر', 85, 3, 6],
        'ASN' => ['Aswan', 'أسوان', 85, 3, 6],
        'BA'  => ['Red Sea', 'البحر الأحمر', 100, 3, 6],
        'MT'  => ['Matrouh', 'مطروح', 100, 3, 6],
        'WAD' => ['New Valley', 'الوادي الجديد', 110, 4, 7],
        'SIN' => ['North Sinai', 'شمال سيناء', 110, 4, 7],
        'JS'  => ['South Sinai', 'جنوب سيناء', 110, 4, 7],
    ];


    /**
     * code => localised name, in the order shown to customers.
     */
    public static function names(string $locale): array
    {
        $index = $locale === 'ar' ? 1 : 0;

        return array_map(fn (array $row) => $row[$index], self::ALL);
    }
}
