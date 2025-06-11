import { Button, Title } from "../atoms";
import { Link } from "react-router-dom";

const Navbar = () => {
    return (
        <nav className="navbar navbar-expand-lg navbar-light bg-light">
            <div className="ms-auto me-4">
                <Link to="/create-goal">
                    <Button
                        type="button"
                        className="btn btn-outline-secondary d-flex d-sm-inline-block"
                    >
                        <Title className="p-1">Ajouter un objectif </Title>
                    </Button>
                </Link>
            </div>
        </nav>
    );
};
export default Navbar;
