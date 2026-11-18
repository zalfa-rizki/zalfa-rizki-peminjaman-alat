<?php
class PetugasController {
    private $db;
    public function __construct($db) { $this->db = $db; }

    private function simpanLog($aksi, $keterangan) {
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $stmt = $this->db->prepare("INSERT INTO log_aktivitas (user_id, aksi, keterangan) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $userId, $aksi, $keterangan);
        $stmt->execute(); $stmt->close();
    }
    private function redirectDashboard($query = '') {
        header('Location: index.php?page=dashboard' . ($query !== '' ? '&' . ltrim($query, '&') : '')); exit;
    }
    private function targetFromPost() {
        $kode = trim((string)($_POST['kode_transaksi'] ?? ''));
        if ($kode !== '' && preg_match('/^[A-Z0-9-]{6,40}$/', $kode)) return ['kode', $kode];
        $id = filter_var($_POST['id_peminjaman'] ?? null, FILTER_VALIDATE_INT);
        if ($id && $id > 0) return ['id', (int)$id];
        return null;
    }
    private function whereTarget($target) {
        return $target[0] === 'kode' ? ['kode_transaksi = ?', 's', $target[1]] : ['id = ?', 'i', $target[1]];
    }

    public function accPeminjaman() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !($target=$this->targetFromPost())) $this->redirectDashboard('aksi=gagal');
        try {
            $this->db->begin_transaction();
            [$where,$type,$value]=$this->whereTarget($target);
            $stmt=$this->db->prepare("SELECT id,alat_id,jumlah,stok_dikurangi FROM peminjaman WHERE {$where} AND status_pinjam='pending' FOR UPDATE");
            $stmt->bind_param($type,$value); $stmt->execute(); $rows=$stmt->get_result(); $ids=[];
            while($r=$rows->fetch_assoc()) $ids[]=$r; $stmt->close();
            if(!$ids) throw new RuntimeException('Tidak ada item pending.');
            $reserve=$this->db->prepare("UPDATE alat SET stok=stok-?, status=CASE WHEN stok-?<=0 THEN 'kosong' ELSE 'tersedia' END WHERE id=? AND stok>=?");
            foreach($ids as $item){
                if((int)$item['stok_dikurangi']===0){
                    $qty=max(1,(int)$item['jumlah']); $alatId=(int)$item['alat_id'];
                    $reserve->bind_param('iiii',$qty,$qty,$alatId,$qty); $reserve->execute();
                    if($reserve->affected_rows!==1) throw new RuntimeException('Stok tidak mencukupi untuk seluruh transaksi.');
                }
            }
            $reserve->close();
            $upd=$this->db->prepare("UPDATE peminjaman SET status_pinjam='disetujui', stok_dikurangi=1 WHERE {$where} AND status_pinjam='pending'");
            $upd->bind_param($type,$value); $upd->execute();
            if($upd->affected_rows !== count($ids)) throw new RuntimeException('Perubahan status tidak lengkap.');
            $upd->close();
            $this->simpanLog('Setujui Peminjaman','Petugas menyetujui transaksi '.($target[0]==='kode'?$target[1]:'ID '.$target[1]).' berisi '.count($ids).' barang.');
            $this->db->commit(); $this->redirectDashboard('status=disetujui');
        } catch(Throwable $e) { try{$this->db->rollback();}catch(Throwable $x){} $this->redirectDashboard('aksi=gagal'); }
    }

    public function tolakPeminjaman() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !($target=$this->targetFromPost())) $this->redirectDashboard('aksi=gagal');
        try {
            $this->db->begin_transaction();
            [$where,$type,$value]=$this->whereTarget($target);
            $stmt=$this->db->prepare("SELECT alat_id,jumlah,stok_dikurangi FROM peminjaman WHERE {$where} AND status_pinjam='pending' FOR UPDATE");
            $stmt->bind_param($type,$value); $stmt->execute(); $res=$stmt->get_result(); $loans=[];
            while($r=$res->fetch_assoc()) $loans[]=$r; $stmt->close();
            if(!$loans) throw new RuntimeException('Tidak ada pengajuan pending.');
            $upd=$this->db->prepare("UPDATE peminjaman SET status_pinjam='ditolak', stok_dikurangi=0 WHERE {$where} AND status_pinjam='pending'");
            $upd->bind_param($type,$value); $upd->execute();
            if($upd->affected_rows !== count($loans)) throw new RuntimeException('Perubahan status tidak lengkap.');
            $upd->close();
            $stock=$this->db->prepare("UPDATE alat SET stok=COALESCE(stok,0)+?, status='tersedia' WHERE id=?");
            foreach($loans as $loan){if((int)$loan['stok_dikurangi']===1){$jumlah=max(1,(int)$loan['jumlah']);$alatId=(int)$loan['alat_id'];$stock->bind_param('ii',$jumlah,$alatId);$stock->execute();if($stock->affected_rows!==1)throw new RuntimeException('Alat tidak ditemukan.');}}
            $stock->close();
            $this->simpanLog('Tolak Peminjaman','Pengajuan transaksi ditolak dan stok seluruh barang dikembalikan.');
            $this->db->commit(); $this->redirectDashboard('status=ditolak');
        } catch(Throwable $e) { try{$this->db->rollback();}catch(Throwable $x){} $this->redirectDashboard('aksi=gagal'); }
    }

    public function prosesKembali() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !($target=$this->targetFromPost())) $this->redirectDashboard('status=kembali_gagal');
        $today=date('Y-m-d'); $finePerDay=20000;
        try {
            $this->db->begin_transaction();
            [$where,$type,$value]=$this->whereTarget($target);
            $stmt=$this->db->prepare("SELECT id,alat_id,jumlah,tanggal_kembali_seharusnya,status_pinjam,stok_dikurangi FROM peminjaman WHERE {$where} AND status_pinjam IN ('disetujui','menunggu_pengembalian') FOR UPDATE");
            $stmt->bind_param($type,$value);$stmt->execute();$res=$stmt->get_result();$loans=[];
            while($r=$res->fetch_assoc())$loans[]=$r;$stmt->close();
            if(!$loans)throw new RuntimeException('Tidak ada barang yang dapat dikembalikan.');
            $upd=$this->db->prepare("UPDATE peminjaman SET tanggal_kembali_realitas=?,status_pinjam='kembali',denda=?,stok_dikurangi=0 WHERE id=? AND status_pinjam IN ('disetujui','menunggu_pengembalian')");
            $stock=$this->db->prepare("UPDATE alat SET stok=COALESCE(stok,0)+?,status='tersedia' WHERE id=?");
            $totalFine=0;
            foreach($loans as $loan){
                $fine=0;$due=$loan['tanggal_kembali_seharusnya']??null;
                if($due && $due!=='0000-00-00' && strtotime($today)>strtotime($due))$fine=max(0,(int)floor((strtotime($today)-strtotime($due))/86400))*$finePerDay;
                $loanId=(int)$loan['id'];$upd->bind_param('sii',$today,$fine,$loanId);$upd->execute();
                if($upd->affected_rows!==1)throw new RuntimeException('Status item berubah.');
                if((int)$loan['stok_dikurangi']===1){$alatId=(int)$loan['alat_id'];$jumlah=max(1,(int)$loan['jumlah']);$stock->bind_param('ii',$jumlah,$alatId);$stock->execute();
                    if($stock->affected_rows!==1)throw new RuntimeException('Alat tidak ditemukan.');}
                $totalFine += $fine;
            }
            $upd->close();$stock->close();
            $this->simpanLog('Proses Pengembalian','Petugas menerima '.count($loans).' item dalam satu transaksi. Total denda: Rp'.number_format($totalFine,0,',','.'));
            $this->db->commit();$this->redirectDashboard('status=selesai&denda='.$totalFine);
        } catch(Throwable $e){try{$this->db->rollback();}catch(Throwable $x){}$this->redirectDashboard('status=kembali_gagal');}
    }
}
