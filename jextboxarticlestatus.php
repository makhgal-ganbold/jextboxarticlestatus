<?php

/**
* @package       JExtBOX Article Status
* @author        Galaa
* @publisher     JExtBOX - BOX of Joomla Extensions (www.jextbox.com)
* @authorUrl     www.galaa.net
* @copyright     copyright (C) 2012-2023 Galaa
* @license       GNU/GPL License - https://www.gnu.org/licenses/gpl.html
*/

// No direct access
defined('_JEXEC') or die;

class plgContentjextboxarticlestatus extends Joomla\CMS\Plugin\CMSPlugin
{

	public function onContentBeforeDisplay($context, &$article, &$params)
	{

		$app = Joomla\CMS\Factory::getApplication();

		// check excluded views

		if ($app->isClient('administrator') || $app->input->get('option', '', 'cmd') != 'com_content' || !in_array($context, array('com_content.article', 'com_content.category', 'com_content.featured')))
			return;

		$categories = $this->params->get('categories', array());
		$categories_selection_type = $this->params->get('categories_selection_type', 'exclude');
		$catid_in = in_array($article->catid, $categories);
		if (($catid_in && $categories_selection_type == 'exclude') || (!$catid_in && $categories_selection_type == 'include'))
			return;

		$articles = trim($this->params->get('articles', ''));
		$articles = empty($articles) ? array() : explode(',', $articles);
		$articleid_in = in_array($article->id, $articles);
		$articles_selection_type = $this->params->get('articles_selection_type', 'exclude');
		if (($articleid_in && ($articles_selection_type == 'exclude')) || (!$articleid_in && ($articles_selection_type == 'include')))
			return;

		$view = $app->input->get('view');
		switch ($view)
		{
			case 'article':
				if (!$this->params->get('show_in_full_view', 1))
					return;
				break;
			case 'featured':
			case 'frontpage':
				if (!$this->params->get('show_in_featured_view', 1))
					return;
				break;
			case 'category':
				if (!$this->params->get('show_in_category_view', 1))
					return;
				break;
			default:
				return;
		}

		// Determine article's badges

		$badges = array();
		$badge_style = $this->params->get('badge_style', 'default');

		// New or Modified

		if ($indicate_new = $this->params->get('indicate_new', 1))
		{
			switch ($badge_style)
			{
				case 'legacy':
					$badge__new = ' <img class="jextbox_article_status_legacy_badge" alt="Just Added" title="" src="plugins/content/jextboxarticlestatus/images/just_added.gif"/> ';
					break;
				case 'custom':
					if (!empty($badge__new = $this->params->get('badge_new_custom', '')))
						break;
				default:
					$badge__new = $this->badge('Just Added', 'primary');
			}
		}
		if ($indicate_modified = $this->params->get('indicate_modified', 1))
		{
			switch ($badge_style)
			{
				case 'legacy':
					$badge__modified = ' <img class="jextbox_article_status_legacy_badge" alt="Modified" title="" src="plugins/content/jextboxarticlestatus/images/edited_recently.gif"/> ';
					break;
				case 'custom':
					if (!empty($badge__modified = $this->params->get('badge_modified_custom', '')))
						break;
				default:
					$badge__modified = $this->badge('Edited Recently', 'success');
			}
		}

		if ($indicate_new)
		{
			$created = strtotime($article->created);
			$articletime = $created;
			$status = $badge__new;
		}
		if ($indicate_modified)
		{
			$modified = strtotime($article->modified);
			$articletime = $modified;
			$status = $badge__modified;
		}
		if ($indicate_new && $indicate_modified)
		{
			if (floor(($modified - $created) / 86400) >= 1)
			{
				$articletime = $modified;
				$status = $badge__modified;
			}
			else
			{
				$articletime = $created;
				$status = $badge__new;
			}
		}
		if (floor((time() - $articletime) / 86400) <= $this->params->get('days', 7))
			$badges[] = $status;

		// Old

		if (empty($badges) && $this->params->get('indicate_old', 1) && ceil((time() - strtotime($article->created)) / 86400) > $this->params->get('days_old', 90))
		{
			switch ($badge_style)
			{
				case 'legacy':
					$badges[] = ' <img class="jextbox_article_status_legacy_badge" alt="Old" title="" src="plugins/content/jextboxarticlestatus/images/too_outdated.gif"/> ';
					break;
				case 'custom':
					$badges[] = $this->params->get('badge_old_custom', '');
					break;
				default:
					$badges[] = $this->badge('Too Outdated', 'warning');
			}
		}

		// Featured

		if (($view == 'category' || $view == 'article') && $this->params->get('indicate_featured', 1) && $article->featured)
		{
			switch ($badge_style)
			{
				case 'legacy':
					$badges[] = '<img class="jextbox_article_status_legacy_badge" alt="Featured" title="" src="plugins/content/jextboxarticlestatus/images/highlighted.gif"/>';
					break;
				case 'custom':
					$badges[] = $this->params->get('badge_featured_custom', '');
					break;
				default:
					$badges[] = $this->badge('Author\'s Highlighted', 'info');
			}
		}

		// Hit or Popular

		if ($this->params->get('indicate_hit', 1) || $this->params->get('indicate_popular', 1))
		{
			$db = Joomla\CMS\Factory::getDBO();
			// The number of articles
			$n_articles = $app->get('jextbox_article_status_n_articles');
			if (is_null($n_articles))
			{
				$query = $db->getQuery(true);
				$query
					->select('COUNT(hits)')
					->from('#__content')
					->where('state=1');
				if (!empty($categories))
					$query
						->where('catid '.($this->params->get('categories_selection_type', 'exclude') == 'exclude' ? 'NOT ' : '') . 'IN ('.implode(',', $categories).')');
				if (!empty($articles))
					$query
						->where('id '.($this->params->get('articles_selection_type', 'exclude') == 'exclude' ? 'NOT ' : '') . 'IN ('.implode(',', $articles).')');
				$db->setQuery($query);
				$n_articles = $db->loadResult();
				if ($view != 'article')
					$app->set('jextbox_article_status_n_articles', $n_articles);
			}
			// Threshold for most hit
			$q_hit = $app->get('jextbox_article_status_q_hit');
			if (is_null($q_hit))
			{
				$query = $db->getQuery(true);
				$query
					->select('hits')
					->from('#__content')
					->where('state=1')
					->order('hits ASC');
				if (!empty($categories))
					$query
						->where('catid '.($this->params->get('categories_selection_type', 'exclude') == 'exclude' ? 'NOT ' : '') . 'IN ('.implode(',', $categories).')');
				if (!empty($articles))
					$query
						->where('id '.($this->params->get('articles_selection_type', 'exclude') == 'exclude' ? 'NOT ' : '') . 'IN ('.implode(',', $articles).')');
				$offset = max(0, min($n_articles - 1, round($n_articles * (double) $this->params->get('badge_hit_limit', 0.95))));
				$query
					->setLimit(1, $offset);
				$db->setQuery($query);
				$q_hit = $db->loadResult();
				if ($view != 'article')
					$app->set('jextbox_article_status_q_hit', $q_hit);
			}
			// Threshold for popular
			$q_popular = $app->get('jextbox_article_status_q_popular');
			if (is_null($q_popular))
			{
				$query = $db->getQuery(true);
				$query
					->select('hits')
					->from('#__content')
					->where('state=1')
					->order('hits ASC');
				if (!empty($categories))
					$query
						->where('catid '.($this->params->get('categories_selection_type', 'exclude') == 'exclude' ? 'NOT ' : '') . 'IN ('.implode(',', $categories).')');
				if (!empty($articles))
					$query
						->where('id '.($this->params->get('articles_selection_type', 'exclude') == 'exclude' ? 'NOT ' : '') . 'IN ('.implode(',', $articles).')');
				$offset = max(0, min($n_articles - 1, round($n_articles * (double) $this->params->get('badge_popular_limit', 0.85))));
				$query
					->setLimit(1, $offset);
				$db->setQuery($query);
				$q_popular = $db->loadResult();
				if ($view != 'article')
					$app->set('jextbox_article_status_q_popular', $q_popular);
			}
			// Most Hit
			if ($this->params->get('indicate_hit', 1) && $article->hits >= $q_hit)
				switch ($badge_style)
				{
					case 'legacy':
						$badges[] = '<img class="jextbox_article_status_legacy_badge" alt="Most Hit" title="" src="plugins/content/jextboxarticlestatus/images/most_read.gif"/>';
						break;
					case 'custom':
						$badges[] = $this->params->get('badge_hit_custom', '');
						break;
					default:
						$badges[] = $this->badge('Most Hit', 'danger');
				}
			elseif ($this->params->get('indicate_popular', 1) && $article->hits >= $q_popular) // Popular
				switch ($badge_style)
				{
					case 'legacy':
						$badges[] = '<img class="jextbox_article_status_legacy_badge" alt="Popular" title="" src="plugins/content/jextboxarticlestatus/images/well_read.gif"/>';
						break;
					case 'custom':
						$badges[] = $this->params->get('badge_popular_custom', '');
						break;
					default:
						$badges[] = $this->badge('Popular', 'warning');
				}
		}

		// No badges
		if (empty($badges))
			return;

		// Finalize
		$badges = implode($badge_style == 'custom' ? '' : ' ', $badges);
		if ($badge_style == 'legacy')
			Joomla\CMS\Factory::getDocument()->addStyleDeclaration('.jextbox_article_status_legacy_badge {border: 0pt none; vertical-align: middle;}');

		// Custom CSS

		if ($this->params->get('wrap', 0))
		{
			$badges = '<div'.(!empty($class = trim($this->params->get('wrapper_css_class_name', ''))) ? ' class="' . $class . '"' : '').'>' . $badges . '</div>';
			if (!empty($css = trim($this->params->get('custom_css', ''))))
				Joomla\CMS\Factory::getDocument()->addStyleDeclaration($css);
		}

		// Badges
		return $badges;

	} // onContentBeforeDisplay

	private function badge ($text, $type)
	{

		return '<span class="badge bg-'.$type.' me-1 badge-'.$type.'">'.$text.'</span>';

	}

} // class

?>
