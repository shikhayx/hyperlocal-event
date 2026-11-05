<?php

declare(strict_types=1);

namespace HiEvents\Console\Commands\Hyperlocal;

use HiEvents\DomainObjects\Enums\EventCategory;
use Illuminate\Http\UploadedFile;

final class HyperlocalImageGenerator
{
    private static array $categoryColors = [
        EventCategory::TECH->value => [[30, 27, 75], [99, 102, 241]],
        EventCategory::WORKSHOP->value => [[15, 23, 42], [56, 189, 248]],
        EventCategory::MUSIC->value => [[88, 28, 135], [217, 70, 239]],
        EventCategory::SPORTS->value => [[6, 78, 59], [52, 211, 153]],
        EventCategory::FOOD_DRINK->value => [[124, 45, 18], [251, 146, 60]],
        EventCategory::EDUCATION->value => [[19, 78, 74], [45, 212, 191]],
        EventCategory::BUSINESS->value => [[30, 41, 59], [148, 163, 184]],
        EventCategory::SOCIAL->value => [[136, 19, 55], [251, 113, 133]],
        EventCategory::FAMILY->value => [[113, 63, 18], [250, 204, 21]],
        EventCategory::HOBBIES->value => [[76, 29, 149], [167, 139, 250]],
        EventCategory::WELLNESS->value => [[6, 95, 70], [110, 231, 183]],
        EventCategory::SPIRITUALITY->value => [[67, 56, 202], [196, 181, 253]],
        EventCategory::OUTDOORS->value => [[20, 83, 45], [134, 239, 172]],
        EventCategory::TOURS->value => [[120, 53, 15], [253, 186, 116]],
        EventCategory::CHARITY->value => [[159, 18, 57], [244, 114, 182]],
        EventCategory::ART->value => [[112, 26, 117], [232, 121, 249]],
        EventCategory::COMEDY->value => [[133, 77, 14], [253, 224, 71]],
        EventCategory::THEATER->value => [[76, 5, 25], [225, 29, 72]],
        EventCategory::FILM->value => [[17, 24, 39], [107, 114, 128]],
        EventCategory::DANCE->value => [[157, 23, 77], [244, 63, 94]],
        EventCategory::FESTIVAL->value => [[126, 34, 206], [245, 158, 11]],
        EventCategory::SEASONAL->value => [[180, 83, 9], [252, 211, 77]],
        EventCategory::NIGHTLIFE->value => [[3, 7, 18], [147, 51, 234]],
        EventCategory::OTHER->value => [[51, 65, 85], [148, 163, 184]],
    ];

    public static function generateCover(EventCategory $category): UploadedFile
    {
        $dir = sys_get_temp_dir().'/hyperlocal_covers';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = 'cover_'.strtolower($category->value).'.jpg';
        $path = $dir.'/'.$filename;

        if (! file_exists($path)) {
            $width = 800;
            $height = 400;
            $image = imagecreatetruecolor($width, $height);

            $colors = self::$categoryColors[$category->value] ?? [[30, 41, 59], [99, 102, 241]];
            [$c1, $c2] = $colors;

            for ($y = 0; $y < $height; $y++) {
                $ratio = $y / (float) $height;
                $r = (int) ($c1[0] + ($c2[0] - $c1[0]) * $ratio);
                $g = (int) ($c1[1] + ($c2[1] - $c1[1]) * $ratio);
                $b = (int) ($c1[2] + ($c2[2] - $c1[2]) * $ratio);

                $lineColor = imagecolorallocate($image, $r, $g, $b);
                imageline($image, 0, $y, $width, $y, $lineColor);
            }

            imagejpeg($image, $path, 88);
            imagedestroy($image);
        }

        return new UploadedFile($path, $filename, 'image/jpeg', null, true);
    }
}
