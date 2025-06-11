
const CreateAGoal = () => {
    return (
        <div className="container">
            <h1 className="my-4">Créer un Objectif</h1>
            <form>
                <div className="mb-3">
                    <label htmlFor="goalTitle" className="form-label">
                        Titre de l'Objectif
                    </label>
                    <input
                        type="text"
                        className="form-control"
                        id="goalTitle"
                        placeholder="Entrez le titre de l'objectif"
                    />
                </div>
                <div className="mb-3">
                    <label htmlFor="goalDescription" className="form-label">
                        Description
                    </label>
                    <textarea
                        className="form-control"
                        id="goalDescription"
                        rows="3"
                        placeholder="Entrez la description de l'objectif"
                    ></textarea>
                </div>
                <button type="submit" className="btn btn-primary">
                    Créer l'Objectif
                </button>
            </form>
        </div>
    );
};
export default CreateAGoal;
