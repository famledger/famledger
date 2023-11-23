<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use App\Service\LiveModeContext;

class LiveModeSwitchController extends AbstractController
{
    #[Route('/liveModeSwitch', name: 'liveModeSwitch')]
    public function index(Request $request, LiveModeContext $context): RedirectResponse
    {
        $context->setLiveMode(!$context->getLiveMode());
        $request->getSession()->set('liveMode', $context->getLiveMode());

        $redirectUrl = urldecode($request->get('redirectUrl'));

        return new RedirectResponse($redirectUrl);
    }
}