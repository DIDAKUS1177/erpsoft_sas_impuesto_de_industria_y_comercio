
class DatosVisuales {

    constructor() { }

    getEmpresa() {
        var nom_empresa = localStorage.getItem('nom_empresa'); 
        console.log(nom_empresa);
      
        $("#nom_empresa").val(nom_empresa);

    }
}
const datosVis = new DatosVisuales();

datosVis.getEmpresa();




