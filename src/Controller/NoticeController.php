<?php

namespace App\Controller;

use App\Audit\Log;
use App\Entity\Notice;
use App\Form\NoticeType;
use App\Repository\NoticeRepository;
use App\Service\LoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class NoticeController extends BaseController
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    #[Route(path: '/admin/notices', name: 'admin_notices')]
    #[Route(path: '/nph/admin/notices', name: 'nph_admin_notices')]
    public function index(Request $request, NoticeRepository $noticeRepository)
    {
        $routePrefix = $request->attributes->get('_route') === 'nph_admin_notice' ? 'nph_' : '';
        $notices = $noticeRepository->findBy([], ['id' => 'asc']);
        return $this->render('notice/index.html.twig', [
            'notices' => $notices,
            'routePrefix' => $routePrefix
        ]);
    }

    #[Route(path: '/admin/notices/notice/{id}', name: 'admin_notice')]
    #[Route(path: '/nph/admin/notices/notice/{id}', name: 'nph_admin_notice')]
    public function edit(NoticeRepository $noticeRepository, LoggerService $loggerService, Request $request, $id = null)
    {
        $routePrefix = $request->attributes->get('_route') === 'nph_admin_notice' ? 'nph_' : '';
        if ($id) {
            $notice = $noticeRepository->find($id);
            if (!$notice) {
                throw $this->createNotFoundException('Page notice not found.');
            }
        } else {
            $notice = new Notice();
            $notice->setStatus(true);
        }

        $form = $this->createForm(NoticeType::class, $notice, ['timezone' => $this->getSecurityUser()->getTimezone()]);

        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                if ($notice->getId() === null) {
                    $this->em->persist($notice);
                    $this->em->flush();
                    $loggerService->log(Log::NOTICE_ADD, $notice->getId());
                    $this->addFlash('success', 'Notice added');
                } elseif ($request->request->has('delete')) {
                    $this->em->remove($notice);
                    $this->em->flush();
                    $loggerService->log(Log::NOTICE_DELETE, $notice->getId());
                    $this->addFlash('success', 'Notice removed');
                } else {
                    $this->em->persist($notice);
                    $this->em->flush();
                    $loggerService->log(Log::NOTICE_EDIT, $notice->getId());
                    $this->addFlash('success', 'Notice updated');
                }
                return $this->redirect($this->generateUrl($routePrefix . 'admin_notices'));
            }
            // Add a form-level error if there are none
            if (count($form->getErrors()) == 0) {
                $form->addError(new FormError('Please correct the errors below'));
            }
        }

        return $this->render('notice/edit.html.twig', [
            'notice' => $notice,
            'settings_return_url' => ($id === null)
                ? $this->generateUrl($routePrefix . 'admin_notices')
                : $this->generateUrl($routePrefix . 'admin_notice', ['id' => $id]),
            'form' => $form->createView(),
            'routePrefix' => $routePrefix
        ]);
    }
}
