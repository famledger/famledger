<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/info')]
class InfoController extends AbstractController
{
    #[Route('/index', name: 'admin_info', methods: ['GET'])]
    public function index(): Response
    {
        $linkSections = [
            'S.A.T.'       => [
                [
                    'caption' => 'Constancia de Situacion Fiscal',
                    'url'     => 'https://wwwmat.sat.gob.mx/operacion/43824/reimprime-tus-acuses-del-rfc'
                ],
                [
                    'caption' => 'Renovacion FIEL',
                    'url'     => 'https://www.sat.gob.mx/tramites/63992/renueva-el-certificado-de-tu-e.firma-(antes-firma-electronica)'
                ],
                [
                    'caption' => 'Descarga CURP',
                    'extra'   => 'MIXJ620503HNERXR06 | MOPM670510MGRNCY09 | MIME971111HNERNM08 | MIMA000408MQRRNLA2',
                    'url'     => 'https://www.gob.mx/curp/'
                ],
                [
                    'caption' => 'Actas de nacimiento',
                    'url'     => 'https://www.gob.mx/ActaNacimiento/',
                    'extra'   => 'MIXJ620503HNERXR06 | MOPM670510MGRNCY09 | MIME971111HNERNM08 | MIMA000408MQRRNLA2',
                ],
            ],
            'EnlaceFiscal' => [
                [
                    'caption' => 'EnlaceFiscal Login',
                    'url'     => 'https://portal.enlacefiscal.com/comprobantes/factura'
                ],
            ],
            'Recibos'      => [
                [
                    'caption' => 'Aguakan - jorgo@miridis.com / 8wHPcL6JC8ktWKsbxMqD',
                    'url'     => 'https://www2.aguakan.com/iniciar-sesion/'
                ],
                [
                    'caption' => 'C.F.E. - jmiridis / OnvECyKVb',
                    'url'     => 'https://app.cfe.mx/Aplicaciones/CCFE/MiEspacio/Login.aspx'
                ],
            ],
        ];

        $localFiles = [
            [
                'caption' => 'FIEL Mayela',
                'path'    => '/Volumes/KC3000-2TB/DataStorage/Family/Documentos personales/Mayela Miridis/FIEL/2026-02'
            ],
            [
                'caption' => 'FIEL Jorgo',
                'path'    => '/Volumes/KC3000-2TB/DataStorage/Family/Documentos personales/Jorgo Miridis/FIEL/2027-01'
            ],
            [
                'caption' => 'Constancia de Situacion Fiscal Mayela',
                'path'    => '/Volumes/KC3000-2TB/DataStorage/Family/Documentos personales/Mayela Miridis/Constancia de Situacion Fiscal'
            ],
            [
                'caption' => 'Constancia de Situacion Fiscal Jorgo',
                'path'    => '/Volumes/KC3000-2TB/DataStorage/Family/Documentos personales/Jorgo Miridis/Constancia de situacion fiscal'
            ],
        ];

        return $this->render('admin/Info/index.html.twig', [
            'linkSections' => $linkSections,
            'localFiles'   => $localFiles,
        ]);
    }
}
