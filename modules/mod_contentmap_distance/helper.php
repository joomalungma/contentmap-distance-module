<?php
/**
 * @copyright	Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// no direct access
defined('_JEXEC') or die;

/**
 * @package		Joomla.Site
 * @subpackage	mod_contentmap_distance
 * @since		2.5
 */
class modContentMapDistanceHelper
{
	/**
	 * Get a list of the menu items.
	 *
	 * @param	JRegistry	$params	The module options.
	 *
	 * @return	array
	 * @since	1.5
	 */
	static function getList(&$params)
	{
        $jinput = JFactory::getApplication()->input;
        $aid = $jinput->get('id','','INT');
        $key = 'menu_items'.$params.$aid;
		$cache = JFactory::getCache('mod_contentmap_distance', '');
		if (!($result = $cache->get($key)))
		{
			// Initialise variables.
			$db			= JFactory::getDbo();

			$numberOfResults		= (int) $params->get('numberOfResults',10);
			$units		= (int)$params->get('units',1);
            $categories = (array)explode(';',$params->get('categories', ''));
            $categories = implode(',',$categories);

            $q = $db->getQuery(true);
            $q->select('id')->select('metadata')->from('#__content')->where('state=1');
            if($categories != 0)
            {
                $q->where('catid in (' . $categories . ')');
            }
            $db->setQuery($q);

            $coords = array();
            foreach ($db->loadObjectList() as $item)
            {
                $item->metadata = new JRegistry($item->metadata);
                $xreference = $item->metadata->get("xreference");
                //$pattern = '/[+-]?([0-9]+)(\.[0-9]+)?,( +)?[+-]?([0-9]+)(\.[0-9]+)?/';
                $pattern = '/[+-]?[0-9]{1,2}([.][0-9]{1,})?[ ]{0,},[ ]{0,}[+-]?[0-9]{1,3}([.][0-9]{1,})?/';
                if (!(bool)preg_match($pattern, $xreference, $matches)) continue;
                $coords[$item->id] = $matches[0];
            }

            // get current article coordinates and remove it from array
            if(empty($coords[$aid]))return;

            $currArticleCoordinat = explode(',', $coords[$aid]);
            $my_lattitude = $currArticleCoordinat[0];
            $my_longitude = $currArticleCoordinat[1];
            unset ($coords[$aid]);


            // From now on, we have a filtered associated list ($coords) with id=>coordinates
            // and the current article's lattitude and longitude
            $distance_unit = ($units == 0) ? JText::_("km") : JText::_("miles");
            foreach($coords as $key => $value) {
                $dets = explode(',', $value);
                $lat2 = $dets[0];
                $lng2 = $dets[1];
                $distances[$key] = modContentMapDistanceHelper::calculateDistance($my_lattitude, $my_longitude, $lat2, $lng2, $units);
            }

            asort($distances);
            $result = array();
            if(version_compare(JVERSION,3) > 0)
            {
                require_once(JPATH_LIBRARIES .  '/legacy/table/content.php');
            }
            else
            {
                require_once(JPATH_LIBRARIES .  '/joomla/database/table/content.php');
            }

            $article = new JTableContent($db);
            foreach(array_slice($distances, 0, $numberOfResults, true) as $key => $value) {
                $article->load($key);
                $result[$key]['link'] = JRoute::_('index.php?option=com_content&amp;view=article&amp;id='.$key.':'.$article->alias.'&amp;catid='.$article->catid);
                $result[$key]['distance'] = round($value, 1) . ' ' . $distance_unit;
                $result[$key]['title'] = $article->title;
            }
            $cache->store($result, $key);
        }
		return $result;
	}
    static function calculateDistance($lat1, $lng1, $lat2, $lng2, $miles = false){
        $pi80 = M_PI / 180;
        $lat1 *= $pi80;
        $lng1 *= $pi80;
        $lat2 *= $pi80;
        $lng2 *= $pi80;

        $r = 6372.797; // mean radius of Earth in km
        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;
        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $km = $r * $c;

        return ($miles ? ($km * 0.621371192) : $km);
    }
}
