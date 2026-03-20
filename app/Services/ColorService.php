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
        return [
            'bgColor'   => null,
            'textColor' => ($roi >= 0 && $roi <= 50)  ? 'red'   : (($roi >= 51 && $roi <= 100) ? 'green' :
                    'pink'),
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

        // Round the value to nearest integer for display
        $display = is_numeric($v) ? number_format(round($v, 0)) : $v;
        return "<span style=\"{$style}\">" . $display . "%</span>";
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

        // Round ROI to nearest integer for display
        $display = is_numeric($value) ? number_format(round($value, 0)) : $value;
        return "<span style=\"{$style}\">" . $display . "%</span>";
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
