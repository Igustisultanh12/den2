<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabel Rekening Simpanan Anggota Koperasi
        if (!Schema::hasTable('koperasi_accounts')) {
            Schema::create('koperasi_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('member_number')->unique(); // KOP-082068
                $table->decimal('balance_simpanan_pokok', 15, 2)->default(0);
                $table->decimal('balance_simpanan_wajib', 15, 2)->default(0);
                $table->decimal('balance_simpanan_sukarela', 15, 2)->default(0);
                $table->decimal('total_simpanan', 15, 2)->default(0);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });
        }

        // 2. Tabel Pinjaman Koperasi
        if (!Schema::hasTable('koperasi_loans')) {
            Schema::create('koperasi_loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('loan_code')->unique(); // PINJ-202609-0001
                $table->string('loan_type')->default('reguler'); // reguler, darurat, barang, pendidikan
                $table->decimal('amount_requested', 15, 2);
                $table->decimal('amount_approved', 15, 2)->nullable();
                $table->decimal('interest_rate_percent', 5, 2)->default(0); // Margin/Jasa per bulan (%)
                $table->integer('duration_months'); // Tenor (3, 6, 10, 12, dll)
                $table->decimal('monthly_installment', 15, 2)->default(0); // Cicilan standar per bulan
                $table->decimal('total_loan_amount', 15, 2)->default(0); // Pokok + Jasa
                $table->decimal('total_paid', 15, 2)->default(0);
                $table->decimal('remaining_amount', 15, 2)->default(0); // Sisa hutang
                $table->text('purpose')->nullable(); // Alasan/Keperluan
                $table->enum('status', ['pending', 'approved', 'rejected', 'active', 'paid_off'])->default('pending');
                $table->text('rejection_reason')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('disbursed_at')->nullable();
                $table->string('document_path')->nullable(); // Berkas lampiran
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 3. Tabel Kartu Angsuran & Riwayat Pembayaran Cicilan
        if (!Schema::hasTable('koperasi_installments')) {
            Schema::create('koperasi_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_id')->constrained('koperasi_loans')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->integer('installment_no')->default(1);
                $table->string('receipt_number')->nullable(); // KW-KOP-202609-0001
                $table->date('due_date')->nullable(); // Jatuh tempo angsuran
                $table->decimal('amount_due', 15, 2)->default(0);
                $table->decimal('amount_paid', 15, 2)->default(0); // Fleksibel nominal berapa pun
                $table->decimal('remaining_loan_after', 15, 2)->default(0);
                $table->date('payment_date')->nullable();
                $table->enum('payment_method', ['potong_gaji', 'transfer', 'tunai'])->default('potong_gaji');
                $table->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid');
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 4. Tabel Transaksi Simpanan (Setoran & Penarikan)
        if (!Schema::hasTable('koperasi_savings_transactions')) {
            Schema::create('koperasi_savings_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('koperasi_accounts')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('transaction_code')->unique(); // TRX-SIMP-202609-0001
                $table->enum('type', ['deposit', 'withdrawal']); // setor atau tarik
                $table->enum('saving_type', ['pokok', 'wajib', 'sukarela']);
                $table->decimal('amount', 15, 2);
                $table->decimal('balance_after', 15, 2);
                $table->enum('payment_method', ['potong_gaji', 'transfer', 'tunai'])->default('tunai');
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 5. Tabel Buku Kas & Mutasi Keuangan Koperasi
        if (!Schema::hasTable('koperasi_cash_mutations')) {
            Schema::create('koperasi_cash_mutations', function (Blueprint $table) {
                $table->id();
                $table->string('transaction_code')->unique();
                $table->date('date');
                $table->enum('type', ['in', 'out']);
                $table->string('category'); // setoran_simpanan, pembayaran_cicilan, pencairan_pinjaman, penarikan_simpanan, dll
                $table->decimal('amount', 15, 2);
                $table->decimal('balance', 15, 2);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('koperasi_cash_mutations');
        Schema::dropIfExists('koperasi_savings_transactions');
        Schema::dropIfExists('koperasi_installments');
        Schema::dropIfExists('koperasi_loans');
        Schema::dropIfExists('koperasi_accounts');
    }
};
