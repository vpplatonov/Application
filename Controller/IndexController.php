<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Application\ICAO\IcaoManagerSoapXml;

use Zend\Paginator;

use Application\Entity\CountriesCountry;
use Doctrine\ORM\EntityManager;

class IndexController extends AbstractActionController
{
    /**
     * @var DoctrineORMEntityManager
     */
    protected $em;

    public function rocketnotamAction()
    {
        // rocket_map_marker_prepare().
        include_once 'RocketNotam.inc';

        $request = $this->getRequest();
        if ($request->isPost()) {
            $ICAO_code = $request->getPost('icao');
            $ICAO_code = explode(',', $ICAO_code);
        }
        else {
            $ICAO_code = array('EGKA');
        }
        $response = $this->getResponse();
        try {
            // OOP approach.
            $manager = new IcaoManagerSoapXml();
            $encoder = $manager->getICAOcoder($ICAO_code);
            $result = $manager->makeICAOrequest($encoder->icaoEncode());
            $decoder = $manager->getICAOdecoder($result);
            $notam_result = $decoder->icaoDecode();

            // GMAP markersprepare.
            $markers = rocket_map_marker_prepare($notam_result);
            $content = json_encode($markers);
        }
        catch(\Exception $e) {
            $content = json_encode(array('error' => $e->getMessage()));
        }
        $response->setContent($content);

        return $response;
    }

    public function getEntityManager()
    {
        if (null === $this->em) {
            $this->em = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
        }
        return $this->em;
    }

    public function indexAction()
    {
       $countries = $this->getEntityManager()->getRepository('Application\Entity\Languages')->findAll();
        if (is_array($countries)) {
            $paginator = new Paginator\Paginator(new Paginator\Adapter\ArrayAdapter($countries));
        } else {
            $paginator = $countries;
        }

        $sm = $this->getServiceLocator();
        $em = $sm->get('doctrine.entitymanager.orm_default');

        // translation
        $translator = $sm->get('mvcTranslator');
        $lang = $translator->getLocale();

        if ($lang != 'en_US') {
            foreach ( $countries as $key => $country ) {
               $country->setLocale($lang);
               $em->refresh($country);
            }
        }

        $paginator->setItemCountPerPage(7);
        $paginator->setCurrentPageNumber($this->getEvent()->getRouteMatch()->getParam('p'));

        $views = new ViewModel(array(
            'countries' => $paginator,
            'h1' => 'Languages',
             // languages field
            'userlistElements' =>array('language', 'name','native', 'prefix','enabled' ),
        ));
        return $views;
    }

    public function changeStatusDb($enable = true,$cid)
    {
        //code save to db
        $em = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');

        $country = $em->find('Application\Entity\CountriesCountry', $cid );

        $country->setEnabled($enable ? 1 : 0);

        /**
         * If country enabled - need to load translation for all languages for this country
         *
         *
        $country_ru = $country->getName();
        $country->setName($country_ru . '  RU');
        $country->setLocale('ru_ru'); // change locale
        */

        // change locale
        $em->persist($country);
        $em->flush();
    }


    public function enablecountryAction()
    {
        $param = 'undefined';

        $param_p = $this->getEvent()->getRouteMatch()->getParam('cid');

        $request = $this->getRequest();
        $response = $this->getResponse();

        if ($request->isPost()){
            $param = $request->getPost();
            $this->changeStatusDb($param->cid == 'enable', $param_p);
        }

        $em = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
        $country = $em->find('Application\Entity\CountriesCountry', $param_p );

        $repository = $em->getRepository('Gedmo\Translatable\Entity\Translation');
        $translations = $repository->findTranslations($country);

        $response->setContent(\Zend\Json\Json::encode(array('param' => $param->cid, 'cid' => $param_p, 'country' =>  $translations)));
        return $response;

    }
}
