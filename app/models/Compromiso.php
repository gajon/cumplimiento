<?php
/**
 * Created by PhpStorm.
 * User: nsilva
 * Date: 13-05-14
 * Time: 11:23
 */

class Compromiso extends Eloquent{

    protected $fillable = array('nombre','url','descripcion','objetivo','publico','avance_descripcion','plazo','presupuesto','departamento');

    public function fuente(){
        return $this->belongsTo('Fuente');
    }

    public function mediosDeVerificacion(){
        return $this->hasMany('MedioDeVerificacion');
    }

    public function usuario(){
        return $this->belongsTo('Usuario');
    }

    public function institucionResposablePlan(){
        return $this->belongsTo('Institucion','institucion_responsable_plan_id');
    }

    public function institucionResposableImplementacion(){
        return $this->belongsTo('Institucion','institucion_responsable_implementacion_id');
    }

    public function sectores(){
        return $this->belongsToMany('Sector');
    }

    public function tags(){
        return $this->belongsToMany('Tag');
    }

    public function hitos(){
        return $this->hasMany('Hito');
    }

    public function actores(){
        return $this->hasMany('Actor');
    }

    public function getAvanceAttribute(){
        $avance=0;
        foreach($this->hitos as $h){
            $avance+=$h->ponderador*$h->avance;
        }
        return $avance;
    }

} 