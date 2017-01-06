<?php
namespace Pmi\Site;

use Silex\Application;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Validator\Constraints;

class Site
{
    protected $app;
    protected $site;

    public function loadSite($siteId, Application $app)
    {
        $site = $app['em']->getRepository('sites')->fetchOneBy([
            'id' => $siteId
        ]);
        if (!$site) {
            return;
        }
        $this->app = $app;
        $this->site = $site;

    }

    public function createEditForm($formFactory)
    {
        if(!$this->site) {
            $this->site = array();
        }
        $formBuilder = $formFactory->createBuilder(FormType::class, $this->site)
            ->add('name', Type\TextType::class, [
                'label' => 'Name',
                'required' => true
            ])
            ->add('google_group', Type\TextType::class, [
                'label' => 'Google Group',
                'required' => true
            ])
            ->add('mayolink_account', Type\TextType::class, [
                'label' => 'MayLink Account',
                'required' => true
            ])
            ->getForm();

        return $formBuilder;

    }

}