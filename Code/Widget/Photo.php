<?php

namespace Code\Widget;

class Photo implements WidgetInterface
{


    /**
     * @brief Widget to display a single photo.
     *
     * @param array $arguments associative array with
     *    * \e string \b src URL of photo; URL must be an http or https URL
     *    * \e boolean \b zrl use zid in URL
     *    * \e string \b style CSS string
     *
     * @return string with parsed HTML
     */

    public function widget(array $arguments): string
    {

        $style = $zrl = false;

        if (array_key_exists('src', $arguments) && isset($arguments['src'])) {
            $url = $arguments['src'];
        }

        if (!str_starts_with($url, 'http')) {
            return '';
        }

        if (array_key_exists('style', $arguments) && isset($arguments['style'])) {
            $style = $arguments['style'];
        }

        // ensure they can't sneak in an eval(js) function

        if (strpbrk($style, '(\'"<>') !== false) {
            $style = '';
        }

        if (array_key_exists('zrl', $arguments) && isset($arguments['zrl'])) {
            $zrl = (($arguments['zrl']) ? true : false);
        }

        if ($zrl) {
            $url = zid($url);
        }

        $o = '<div class="widget">';

        $o .= '<img ' . (($zrl) ? ' class="zrl" ' : '')
            . (($style) ? ' style="' . $style . '"' : '')
            . ' src="' . $url . '" alt="' . t('photo/image') . '">';

        $o .= '</div>';

        return $o;
    }
}
