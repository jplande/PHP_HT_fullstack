import { Button, Title } from "../atoms";

const Navbar = () => {
    return (
        <nav className="navbar navbar-expand-lg navbar-light bg-light">
            <div className="ms-auto me-4">
                <Button
                    type="button"
                    className="btn btn-outline-secondary d-flex d-sm-inline-block"
                    onClick={() => console.log("Objectif ajouté")}
                >
                    <Title className="p-1">Ajouter un objectif </Title>
                </Button>
            </div>
        </nav>
    );
};
export default Navbar;
