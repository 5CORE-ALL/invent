<?php

namespace App\Services;

class ColorService
{
    /**
     * Get background and text colors based on ROI value.
     *
     * @param float $roi
     * @return array
     */
    public function getRoiColors($roi)
    {
        $bgColor = null;
        $textColor = null;

        if ($roi < 11) {
            $textColor = '#ff0000'; // red
        } elseif ($roi >= 10 && $roi < 15) {
            $bgColor = 'yellow';
            $textColor = '#000000'; // black
        } elseif ($roi >= 15 && $roi < 20) {
            $textColor = '#0d6efd'; // blue
        } elseif ($roi >= 21 && $roi < 50) {
            $textColor = '#198754'; // green
        } else {
            $textColor = '#800080'; // purple
        }

        return [
            'bgColor' => $bgColor,
            'textColor' => $textColor,
        ];
    }

    /**
     * Get HTML string for value v.
     *
     * @param float $v
     * @return string
     */
    public function getValueHtml($v)
    {
        $style = 'font-weight:600;';

        if ($v < 0) {
            $style .= 'color:#ff0000;'; // red for negative
        } elseif ($v < 11) {
            $style .= 'color:#ff0000;'; // red
        } elseif ($v >= 11 && $v < 15) {
            $style .= 'background:yellow;color:#000000;padding:2px 6px;border-radius:4px;'; // yellow bg, black text
        } elseif ($v >= 15 && $v < 20) {
            $style .= 'color:#0d6efd;'; // blue
        } elseif ($v >= 21 && $v < 50) {
            $style .= 'color:#198754;'; // green
        } else {
            $style .= 'color:#800080;'; // purple
        }

        return "<span style=\"{$style}\">" . $v . "%</span>";
    }

    /**
     * Get HTML string for ROI value for the view.
     *
     * @param float $value
     * @return string
     */
    public function getRoiHtmlForView($value)
    {
        $style = 'font-weight:600;';

        if ($value < 0) {
            $style .= 'color:red;'; // red for negative
        } elseif ($value >= 0 && $value <= 3) {
            $style .= 'color:red;';
        } elseif ($value > 3 && $value <= 6) {
            $style .= 'background:yellow;color:black;padding:2px 6px;border-radius:4px;';
        } elseif ($value > 6 && $value <= 9) {
            $style .= 'color:blue;';
        } elseif ($value > 9 && $value <= 13) {
            $style .= 'color:green;';
        } elseif ($value > 14) {
            $style .= 'color:purple;';
        }

        return "<span style=\"{$style}\">" . $value . "%</span>";
    }

    /**
     * Get HTML string for CVR value.
     *
     * @param float $value
     * @return string
     */
    public function getCvrHtml($value)
    {
        $style = 'font-weight:600;';

        if ($value >= 0 && $value <= 3) {
            $style .= 'color:red;';
        } elseif ($value > 3 && $value <= 6) {
            $style .= 'background:yellow;color:black;padding:2px 6px;border-radius:4px;';
        } elseif ($value > 6 && $value <= 9) {
            $style .= 'color:blue;';
        } elseif ($value > 9 && $value <= 13) {
            $style .= 'color:green;';
        } elseif ($value > 14) {
            $style .= 'color:purple;';
        }

        return "<span style=\"{$style}\">" . number_format($value, 1) . "%</span>";
    }
}