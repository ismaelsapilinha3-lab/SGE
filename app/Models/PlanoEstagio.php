<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlanoEstagio extends Model
{
    use HasFactory;

    protected $table = 'planos_estagio';

    protected $primaryKey = 'id_plano_estagio';

    protected $fillable = [
        'id_estagio',
        'id_supervisor',
        'nome_arquivo',
        'caminho_arquivo',
        'formato',
        'tamanho',
        'id_autor',
        'versao',
        'data_upload',
        'objetivos',
        'atividades_previstas',
        'cronograma',
        'data_inicio',
        'data_fim',
        'estado',
        'motivo_recusa',
        'id_coordenador',
        'data_decisao',
    ];

    /**
     * Formatos aceites no plano de estágio.
     */
    public const FORMATOS_ACEITES = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    protected function casts(): array
    {
        return [
            'data_upload' => 'datetime',
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'data_decisao' => 'datetime',
            'versao' => 'integer',
            'tamanho' => 'integer',
        ];
    }

    public function estagio(): BelongsTo
    {
        return $this->belongsTo(
            Estagio::class,
            'id_estagio',
            'id_estagio'
        );
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_supervisor',
            'id_utilizador'
        );
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_autor',
            'id_utilizador'
        );
    }

    public function coordenador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_coordenador',
            'id_utilizador'
        );
    }

    public function historico(): HasMany
    {
        return $this->hasMany(
            PlanoEstagioHistorico::class,
            'id_plano_estagio',
            'id_plano_estagio'
        );
    }

    public function estaSubmetido(): bool
    {
        return $this->estado === 'submetido';
    }

    public function estaAprovado(): bool
    {
        return $this->estado === 'aprovado';
    }

    /**
     * Nome do formato apresentado ao utilizador (PDF, Word, ...).
     */
    public function formatoLegivel(): string
    {
        return match (strtolower((string) $this->formato)) {
            'pdf' => 'PDF',
            'doc' => 'Word (DOC)',
            'docx' => 'Word (DOCX)',
            default => strtoupper((string) $this->formato) ?: 'Documento',
        };
    }

    /**
     * MIME usado ao servir o ficheiro do plano.
     */
    public function tipoConteudo(): string
    {
        $formato = strtolower((string) $this->formato);

        if (isset(self::FORMATOS_ACEITES[$formato])) {
            return self::FORMATOS_ACEITES[$formato];
        }

        $mime = $this->caminho_arquivo
            ? Storage::disk('local')->mimeType($this->caminho_arquivo)
            : null;

        return $mime ?: 'application/octet-stream';
    }

    /**
     * Indica se o ficheiro existe no disco local.
     */
    public function ficheiroDisponivel(): bool
    {
        return (bool) $this->caminho_arquivo
            && Storage::disk('local')->exists($this->caminho_arquivo);
    }

    /**
     * Nome seguro para enviar ao navegador.
     */
    public function nomeSeguro(): string
    {
        $nome = $this->nome_arquivo
            ?: 'plano-estagio.'.(strtolower((string) $this->formato) ?: 'pdf');

        return Str::of($nome)->replace(['"', "\r", "\n"], '')->toString();
    }

    /**
     * Guarda uma cópia do conteúdo atual antes de o plano ser editado
     * e reenviado, preservando o rasto das versões anteriores.
     */
    public function registarHistorico(?int $alteradoPor = null): PlanoEstagioHistorico
    {
        return $this->historico()->create([
            'objetivos' => $this->objetivos ?? '',
            'atividades_previstas' => $this->atividades_previstas ?? '',
            'cronograma' => $this->cronograma ?? '',
            'data_inicio' => $this->data_inicio,
            'data_fim' => $this->data_fim,
            'estado' => $this->estado,
            'motivo_recusa' => $this->motivo_recusa,
            'alterado_por' => $alteradoPor,
            'alterado_em' => now(),
        ]);
    }
}
