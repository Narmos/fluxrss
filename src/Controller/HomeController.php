<?php

namespace App\Controller;

use Exception;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{
    //private $fluxUrls = array();

    /**
     * @Route("/", name="home", methods="GET|POST")
     */
    public function index(Request $request)
    {
        // Initialisation des variables
        $fluxUrls = array(); // tableau pour stocker la/les url(s) ajoutée(s)
        $fluxUrlsToShow = array(); // tableau pour stocker la/les url(s) à afficher
        $fluxContent = array(); // tableau pour stocker la/les url(s) ajoutée(s) avec leur(s) objet(s)
        $fluxContentToShow = array(); // tableau pour stocker le/les url(s) à afficher avec leur(s) objet(s)
        $newFluxError = false;

        // On récupère le tableau du cookie "fluxRSSUrls" si il existe, sinon on le créé
        if ($request->cookies->has('fluxRSSUrls')) {
            $fluxUrls = json_decode($request->cookies->get('fluxRSSUrls'));
        } else {
            $this->setFluxCookie($fluxUrls);
        }

        // Ajout d'un flux RSS
        if ($request->get('add_url') !== null) {
            $addUrl = $request->get('add_url');

            // Regex pour matcher une URL FTP
            $ftpRegex = '%^(?:(?:ftps?)://)(?:\S+(?::\S*)?@|\d{1,3}(?:\.\d{1,3}){3}|(?:(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)(?:\.(?:[a-z\d\x{00a1}-\x{ffff}]+-?)*[a-z\d\x{00a1}-\x{ffff}]+)*(?:\.[a-z\x{00a1}-\x{ffff}]{2,6}))(?::\d+)?(?:[^\s]*)?$%iu';

            // Si URL http(s) saisie, on récupère le flux RSS
            if (!preg_match($ftpRegex, $addUrl)) {
                try {
                    // On teste la conversion du document XML en objet SimpleXMLElement
                    simplexml_load_file($addUrl);
                    // On ajoute l'url dans la liste si elle n'existe pas
                    if (!in_array($addUrl, $fluxUrls)) {
                        $fluxUrls[] = $addUrl;
                    }
                } catch (Exception $e) {
                    $newFluxError = true;
                }
            } else {
                $newFluxError = true;
            }
        }

        // Suppression d'un flux RSS
        if ($request->get('del_url') !== null) {
            $delUrl = $request->get('del_url');

            // Si l'url existe dans le tableau, on la supprime
            if (($key = array_search($delUrl, $fluxUrls)) !== false) {
                unset($fluxUrls[$key]);
                //$fluxUrlsToShow = $fluxUrls;
            }
            if (($key = array_search($delUrl, $fluxUrlsToShow)) !== false) {
                unset($fluxUrlsToShow[$key]);
            }
        }

        // Récupération du/des flux(s) à afficher
        if ($request->get('selection') !== null) {
            unset($fluxUrlsToShow);
            $selection = $request->get('selection');
            if ($selection == 'all') {
                $fluxUrlsToShow = $fluxUrls;
            } else {
                $fluxUrlsToShow[0] = $selection;
            }
        } else {
            $fluxUrlsToShow = $fluxUrls;
            // à cause de cela, si on supprimer un flux, on revient sur l'affichage de tous les fluxs
        }

        // Récupération du contenu du/des flux(s) à lister et afficher
        if ($fluxUrls !== null && $fluxUrlsToShow !== null) {
            $fluxContent = $this->getFluxContent($fluxUrls);
            $fluxContentToShow = $this->getFluxContent($fluxUrlsToShow);
        }

        // Mise à jour du cookie
        $this->setFluxCookie($fluxUrls);

        // Affichage de la page
        return $this->render('home/home.html.twig', [
            'fluxUrls' => $fluxUrls,
            'fluxUrlsToShow' => $fluxUrlsToShow,
            'fluxContent' => $fluxContent,
            'fluxContentToShow' => $fluxContentToShow,
            'newFluxError' => $newFluxError,
        ]);
    }

    /**
     * @Route("/import", name="import")
     */
    public function importUrls(Request $request)
    {
        // Affichage de la page
        return $this->render('home/import.html.twig');
    }

    /**
     * @Route("/export", name="export")
     */
    public function exportUrls(Request $request)
    {
        //$json = json_encode($this->fluxUrls);
        //header('Content-disposition: attachment; filename=jsonFile.json');
        //header('Content-type: application/json');
        //echo( $json);
        //return $this->redirectToRoute("home");
        // Affichage de la page
        return $this->render('home/export.html.twig');
    }

    /**
     * Génération d'un cookie
     * Nom : fluxRSSUrls
     * Valeur : objet json contenant les urls
     * Validité : 10 ans
     */
    public function setFluxCookie($fluxUrls) {
        $request = new Request();
        $cookie = new Cookie('fluxRSSUrls', json_encode($fluxUrls), time() + (10 * 365 * 24 * 60 * 60), '/', $request->server->get('HTTP_HOST'), true, true);
        $response = new Response();
        $response->headers->setCookie($cookie);
        $response->sendHeaders();
    }

    /**
     * Récupération du contenu d'un flux
     */
    public function getFluxContent($urls) {
        $content = array();
        foreach ($urls as $url) {
            $content[$url] = simplexml_load_file($url);
        }
        return $content;
    }
}
