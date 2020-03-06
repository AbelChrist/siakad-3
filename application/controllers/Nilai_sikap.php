<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Helper\Sample;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Nilai_sikap extends CI_Controller {
    public function __construct(){
        parent::__construct();
        
        $this->load->model('Nilai_sikap_model');
        $this->load->model('Kelas_model');
        // cek user login
        check_login();
    }
    
    public function index()
    {
        $data['kelas'] = $this->Nilai_sikap_model->get_kelas();
        // cek apakah guru tersebut walikelas
        if(user_info()['is_walikelas'] == 'yes'){
            $data['walikelas'] = $this->Nilai_sikap_model->get_walikelas();
        }
        // print_r($data['walikelas']);

        $this->load->view('template/header');
        $this->load->view('template/sidebar');
        $this->load->view('nilai_sikap/index', $data);
        $this->load->view('template/footer');
    }

    // lakukan penilaian pada kelas yang diajar
    public function do_nilai($id_kelas)
    {
        $data['siswa'] = $this->Nilai_sikap_model->get_siswa($id_kelas);
        // print_r(count($data['siswa']));
        $this->load->view('template/header');
        $this->load->view('template/sidebar');
        $this->load->view('nilai_sikap/do_nilai', $data);
        $this->load->view('template/footer');
    }

    // simpan hasil penilaian
    public function simpan()
    {
        $id_siswa = $this->input->post('id_siswa[]');
        $id_guru = user_info()['id_guru'];
        $nilai = $this->input->post('nilai[]');

        $data = array();
        for($i=0; $i < count($id_siswa); $i++)
        {
            array_push($data, array(
                'id_tahun' => $_SESSION['id_tahun_pelajaran'],
                'id_siswa' => $id_siswa[$i],
                'id_guru' => $id_guru,
                'nilai' => $nilai[$i],
            ));
        }

        $this->db->insert_on_duplicate_update_batch('nilai_sikap', $data);
        redirect('nilai_sikap');
    }

    // cek nilai rombel walikelas
    public function cek_nilai_walikelas()
    {
        $data_kelas = [];
        $kelas = $this->Nilai_sikap_model->get_walikelas();
        foreach ($kelas as $k) {
            $cek = $this->Nilai_sikap_model->cek_nilai_siswa($k['id_kelas']);
            $data_nya = array(
                'id_kelas' => $k['id_kelas'],
                'datanya' => [
                    'jumlah' => $cek['jumlah'],
                    'sudah_dinilai' => $cek['sudah_dinilai'],
                    'belum_dinilai' => $cek['belum_dinilai'],
                ]
            );
            array_push($data_kelas, $data_nya);
        }
        header('Content-Type: application/json');
        echo json_encode($data_kelas);
    }

    // cek nilai kelas yang diajar
    public function cek_nilai()
    {
        $data_kelas = [];
        $kelas = $this->Nilai_sikap_model->get_kelas();
        foreach ($kelas as $k) {
            $cek = $this->Nilai_sikap_model->cek_nilai_siswa($k['id_kelas']);
            $data_nya = array(
                'id_kelas' => $k['id_kelas'],
                'datanya' => [
                    'jumlah' => $cek['jumlah'],
                    'sudah_dinilai' => $cek['sudah_dinilai'],
                    'belum_dinilai' => $cek['belum_dinilai'],
                ]
            );
            array_push($data_kelas, $data_nya);
        }
        header('Content-Type: application/json');
        echo json_encode($data_kelas);
    }

    // download file excel
    public function download($id_kelas)
    {
        
        $nama_user = user_info()['first_name'];
        $id_guru = user_info()['id_guru'];
        
        $helper = new Sample();
        if ($helper->isCli()) {
            $helper->log('This example should only be run from a Web Browser' . PHP_EOL);
            return;
        }
        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();

        // Set document properties
        $spreadsheet->getProperties()->setCreator('Teguh Permadi')
            ->setLastModifiedBy($nama_user)
            ->setTitle('Office 2007 XLSX Download Nilai Sikap')
            ->setSubject('Office 2007 XLSX Download Nilai Sikap')
            ->setDescription('Download Nilai Sikap for Office 2007 XLSX, generated using PHP classes.')
            ->setKeywords('office 2007 openxml php')
            ->setCategory('Siakad Excel');

        // Add some data
        $data_siswa = $this->Nilai_sikap_model->get_siswa($id_kelas);
        $data_kelas = $this->Kelas_model->get_kelas($id_kelas);

        $filename = '"Nilai Sikap Kelas '.$data_kelas['nama'].' oleh '.user_info()['first_name'].' '.user_info()['last_name'].'.xlsx"';

        // rapikan dulu datanya
        $siswa = [];
        foreach($data_siswa as $ds){
            array_push($siswa, [
                'nama_siswa' => $ds['nama_siswa'],
                'id_tahun' => $_SESSION['id_tahun_pelajaran'],
                'id_guru' => user_info()['id_guru'],
                'id_siswa' => $ds['id_siswa'],
                'nilai' => $ds['nilai']
            ]);
        }
        ## Setting a range of cells from an array
        $identitas = [
            // tulis identitas tahun pelajaran, guru, dan kelas
            ['Tahun Pelajaran', null, null, null, $_SESSION['tahun']],
            ['Semester', null, null, null, $_SESSION['semester']],
            ['Nama Guru', null, null, null,  user_info()['first_name'].' '.user_info()['last_name']],
            ['Kelas yang dinilai',  null, null, null, $data_kelas['nama']],
            ['Jenis Penilaian',  null, null, null, 'Penilaian Sikap'],
            [null,  null, null, null, null],
            ['Nama Siswa', 'id_tahun', 'id_guru', 'id_siswa', 'Nilai']
        ];

        $data = array_merge($identitas, $siswa);
        
        // keterangan dalam file excel
        $data_keterangan = [
            ['Keterangan'],
            ['4', 'Sangat Baik'],
            ['3', 'Baik'],
            ['2', 'Cukup'],
            ['1', 'Kurang Baik'],
        ];

        // tuliskan array ke dalam excel
        $spreadsheet->getActiveSheet()
            ->fromArray(
                $data,  // The data to set
                NULL,        // Array values with this value will not be set
                'A1'         // Top left coordinate of the worksheet range where
                             //    we want to set these values (default is A1)
            );

         $spreadsheet->getActiveSheet()
            ->fromArray(
                $data_keterangan,  // The data to set
                NULL,        // Array values with this value will not be set
                'J1'         // Top left coordinate of the worksheet range where
                             //    we want to set these values (default is A1)
            );

        // rubah ukuran kolom A
        $spreadsheet->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);

        // sembunyikan kolom 
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setVisible(false);
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setVisible(false);
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setVisible(false);

        // Rename worksheet
        $spreadsheet->getActiveSheet()->setTitle('Nilai Sikap');

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $spreadsheet->setActiveSheetIndex(0);

        // Redirect output to a client’s web browser (Xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename='.$filename);
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');

        redirect('nilai_sikap');
    }
}