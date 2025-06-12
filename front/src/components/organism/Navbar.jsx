import { Button, Title } from "../atoms";
import React, { useState } from "react";
// import { Link } from "react-router-dom";
import CreateGoalModal from "./ CreateGoalModal";

const Navbar = () => {
    const [showModal, setShowModal] = useState(false);

    const handleSaveGoal = (goal) => {
        console.log("Goal saved:", goal);
        setShowModal(false);
    }

    return (
        <nav className="navbar navbar-expand-lg navbar-light bg-light">
            <div className="ms-auto me-4">
                {/* <Link to="/create-goal"> */}
                <Button
                    type="button"
                    className="btn btn-outline-secondary d-flex d-sm-inline-block"
                    onClick={() => setShowModal(true)}
                >
                    <Title className="p-1">Ajouter un objectif </Title>
                </Button>
                {/* </Link> */}
                <CreateGoalModal
                    show={showModal}
                    onHide={() => setShowModal(false)}
                    onSave={handleSaveGoal}
                />
            </div>
        </nav>
    );
};
export default Navbar;
