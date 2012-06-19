<?php
/*
 * This file is part of the AlphaLemon CMS Application and it is distributed
 * under the GPL LICENSE Version 2.0. To use this application you must leave
 * intact this copyright notice.
 *
 * Copyright (c) AlphaLemon <webmaster@alphalemon.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://www.alphalemon.com
 *
 * @license    GPL LICENSE Version 2.0
 *
 */

namespace AlphaLemon\AlphaLemonCmsBundle\Core\Listener\Language;

use AlphaLemon\AlphaLemonCmsBundle\Core\Content\Block\AlBlockManager;
use AlphaLemon\AlphaLemonCmsBundle\Core\Event\Content\Language\BeforeAddLanguageCommitEvent;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Listen to the onBeforeAddLanguageCommit event to copy blocks from a language
 * to the adding language
 *
 * @author AlphaLemon <webmaster@alphalemon.com>
 */
class AddLanguageBlocksListener extends Base\AddLanguageBaseListener
{
    private $blockManager;
    private $router;

    /**
     * Constructor
     *
     * @param AlBlockManager $blockManager
     */
    public function __construct(AlBlockManager $blockManager, ContainerInterface $container = null)
    {
        parent::__construct($container);

        if(null !== $container) {
            $this->router = $container->get('routing');
        }
        $this->blockManager = $blockManager;
    }

    /**
     * {@inheritdoc}
     *
     * @return A model collection instance depending on the used ORM (i.e PropelCollection)
     */
    protected function setUpSourceObjects()
    {
        $baseLanguage = $this->getBaseLanguage();

        return $this->blockManager
                        ->getBlockModel()
                        ->fromLanguageId($baseLanguage->getId());
    }

    /**
     * {@inheritdoc}
     *
     * @param array $values
     * @return boolean
     */
    protected function copy(array $values)
    {
        unset($values['Id']);
        unset($values['CreatedAt']);
        $values['HtmlContent'] = $this->configurePermalinkForNewLanguage($values['HtmlContent']);
        $values['LanguageId'] = $this->languageManager->get()->getId();
        $result = $this->blockManager
                    ->set(null)
                    ->save($values);

        return $result;
    }

    /**
     * Configures the permalink for the new language.
     * 
     * The content is parsed to find links. When at least a link is found it is retrieved and matched to find
     * if it is an internal link. When it is an internal link, it is prefixed with the new language as follows:
     * [new_language]-[permalink], otherwise it is left untouched
     *
     * @param string $content
     * @return string
     */
    protected function configurePermalinkForNewLanguage($content)
    {
        $router = $this->router;
        
        if(null === $this->languageManager || null === $router) {
            return $content;
        }
        
        $languageName =  $this->languageManager->get()->getLanguage();
        $content = preg_replace_callback('/(\<a[^\>]+href[="\'\s]+)([^"\'\s]+)?([^\>]+\>)/s', function ($matches) use($router, $languageName) {

            $url = $matches[2];
            try
            {
                $tmpUrl = (empty($match) && substr($url, 0, 1) != '/') ? '/' . $url : $url;
                $params = $router->match($tmpUrl);

                $url = (!empty($params)) ? $languageName . '-' . $url : $url;
            }
            catch(ResourceNotFoundException $ex)
            {
                // Not internal route the link remains the same
            }

            return $matches[1] . $url . $matches[3];
        }, $content);
        
        return $content;
    }
}

