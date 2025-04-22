<?php

namespace App\Controller;

use App\Repository\{
    UserRepository,
    MessageRepository,
    BlogPostRepository,
    ForumRepository,
    ForumResponseRepository,
    EventRepository
};
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports')]
#[IsGranted('ROLE_ADMIN')]
class ReportController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MessageRepository $messageRepository,
        private readonly BlogPostRepository $blogPostRepository,
        private readonly ForumRepository $forumRepository,
        private readonly ForumResponseRepository $forumResponseRepository,
        private readonly EventRepository $eventRepository,
        private readonly EntityManagerInterface $em
    ) {}

    #[Route('/engagement', name: 'app_admin_reports_engagement')]
    public function engagementReport(Request $request): Response
    {
        $format = $request->query->get('format', 'pdf');

        // Collecter les statistiques
        $stats = [
            'total_utilisateurs_actifs' => $this->userRepository->count([]),
            'total_echanges_realisés' => $this->messageRepository->count([]),
            'total_blog_postés' => $this->blogPostRepository->count([]),
            'total_forum_crées' => $this->forumRepository->count([]),
            'total_forum_responses_crées' => $this->forumResponseRepository->count([]),
            'total_evenements_crées' => $this->eventRepository->count([]),
        ];

        if ($format === 'pdf') {
            // Configuration PDF
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Arial');

            $dompdf = new Dompdf($pdfOptions);
            $html = $this->renderView('admin/reports/engagement_pdf.html.twig', [
                'stats' => $stats,
                'generation_date' => new \DateTime()
            ]);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return new Response(
                $dompdf->output(),
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="rapport_engagement_' . date('Ymd_His') . '.pdf"'
                ]
            );
        }

        // Format CSV
        $csvData = [['Statistique', 'Valeur']];
        foreach ($stats as $label => $value) {
            $csvData[] = [ucfirst(str_replace('_', ' ', $label)), $value];
        }

        return $this->renderCsvResponse($csvData, 'rapport_engagement_' . date('Ymd_His') . '.csv');
    }

    #[Route('/export-users', name: 'app_admin_reports_export_users')]
    public function exportUsers(): Response
    {
        $users = $this->userRepository->findAllNonAnonymous();

        $csvData = [[
            'ID',
            'Pseudonyme',
            'Email',
            'Rôles',
            'Vérifié',
            'Date création',
            'Compétences'
        ]];

        foreach ($users as $user) {
            $csvData[] = [
                $user->getId(),
                $user->getPseudo(),
                $user->getEmail(),
                implode(', ', $user->getRoles()),
                $user->isVerified() ? 'Oui' : 'Non',
                $user->getCreatedAt()->format('d/m/Y H:i'),
                implode(', ', $user->getSkills()->map(fn($s) => $s->getName())->toArray())
            ];
        }

        return $this->renderCsvResponse($csvData, 'export_utilisateurs_' . date('Ymd_His') . '.csv');
    }

    private function renderCsvResponse(array $data, string $filename): Response
    {
        $csvContent = '';
        foreach ($data as $row) {
            $csvContent .= implode(';', array_map(function ($item) {
                return '"' . str_replace('"', '""', $item) . '"';
            }, $row)) . "\r\n";
        }

        return new Response(
            $csvContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]
        );
    }
}
